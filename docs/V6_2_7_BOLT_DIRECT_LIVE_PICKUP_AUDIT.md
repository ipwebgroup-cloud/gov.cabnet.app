# v6.2.7 — Bolt direct live pickup audit

Date: 2026-05-08
Project: gov.cabnet.app Bolt → EDXEIX bridge

## Purpose

This patch replaces the previous `bolt_live_order_audit.php` with a direct live Bolt API audit.

The previous v6.2.6 audit was useful, but its output was based mainly on rows already stored in `bolt_raw_payloads`. That made the timing evidence ambiguous because `raw_payload_captured_at` can be server-timezone dependent and many rows are historical finished/cancelled rides.

The v6.2.7 audit calls `gov_bolt_get_fleet_orders()` directly, does not store the payloads, and stamps every observed row with:

- `observed_at_eest`
- `observed_at_utc`
- `source = live_api_direct_no_store`
- `state_analysis.status_group`
- `pickup_receipt_readiness.would_be_candidate_if_worker_saw_this_now`

## Safety

The audit script:

- does not call EDXEIX
- does not create `submission_jobs`
- does not create `submission_attempts`
- does not issue AADE/myDATA receipts
- does not store Bolt payloads
- does not print credentials, tokens, cookies, or full raw payloads

## Decisive proof row

During a real live ride, the row we need to see is:

```json
"state_analysis": {
  "status_group": "active_picked_up_before_finish",
  "pickup_receipt_probe_candidate": true
},
"pickup_receipt_readiness": {
  "would_be_candidate_if_worker_saw_this_now": true
}
```

This means Bolt `getFleetOrders` exposes pickup state before finish and the pickup receipt worker can issue at pickup if it sees the same evidence.

## Non-conclusive rows

Finished historical rows are useful for matching and sanity checks, but they do not prove pickup-time visibility.

Rows with statuses such as the following must remain blocked even if a pickup timestamp exists:

- `client_cancelled`
- `driver_cancelled_after_accept`
- `driver_did_not_respond`
- `client_did_not_show`
- `no_show`
- `cancelled`

## Commands

Single live API poll:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php --hours=24 --limit=80
```

Watch during the next live ride:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php --watch --sleep=30 --hours=24 --limit=80
```

Show only rows that would be pickup-receipt candidates:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php --watch --sleep=15 --hours=24 --limit=80 --only-candidates
```

## Next decision

If the live direct audit shows a pickup candidate before finish:

1. Confirm `sync_bolt.php` also sees/stores the active picked-up row.
2. Patch or tune the pickup worker if needed.
3. Keep EDXEIX queues untouched.

If the live direct audit never shows a pickup candidate until after finish:

1. `getFleetOrders` is not sufficient for pickup-time receipts.
2. We must investigate Bolt webhook/event/current-order options or another operational trigger.
