# gov.cabnet.app v6.3.0 — EDXEIX Pre-live Hardening

## Purpose

Move closer to controlled live EDXEIX submission without enabling automatic live submission.

This patch keeps the current successful AADE receipt workflow intact, then hardens the EDXEIX gate so receipt-only Bolt mail bookings can never be submitted to EDXEIX.

## Production context

Current stable receipt flow:

```text
Bolt pre-ride email
→ import_bolt_mail.php
→ bolt_mail_receipt_worker.php v6.2.9/v6.3.0
→ AADE receipt at/after pickup time
→ driver PDF email
```

Current EDXEIX rule:

```text
No live EDXEIX submission unless Andreas explicitly performs a one-shot live submit on a real eligible future Bolt API booking.
```

## Changes

### 1. EDXEIX live gate hardening

File:

```text
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
```

Changes:

- Adds `gov_live_is_receipt_only_booking()`.
- Treats `source_system=bolt_mail`, `order_reference=mail:*`, and receipt-only block reasons as EDXEIX-ineligible.
- Adds blocker `receipt_only_booking_blocked_from_edxeix`.
- Adds unsafe terminal status recognition for:
  - `client_did_not_show`
  - `driver_did_not_respond`
  - `no_show`
  - related no-show variants.

### 2. Receipt worker safety alignment

File:

```text
gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
```

Changes:

- Version label updated to v6.3.0.
- Newly created receipt-only bookings now set:

```text
never_submit_live = 1
edxeix_ready = 0
live_submit_block_reason = aade_receipt_only_no_edxeix_submission_allowed
```

This does not change AADE receipt issuing or driver email behavior.

### 3. EDXEIX pre-live audit CLI

File:

```text
gov.cabnet.app_app/cli/edxeix_prelive_audit.php
```

Read-only tool to inspect whether any booking is close to EDXEIX live eligibility.

It never submits, never creates jobs, never prints cookies/tokens, and never issues receipts.

### 4. SQL safety migration

File:

```text
gov.cabnet.app_sql/2026_05_08_v6_3_0_receipt_only_edxeix_block.sql
```

Marks existing receipt-only `bolt_mail` bookings as never-submit-live.

## Validation commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php
```

Run the SQL after backup:

```bash
mysqldump cabnet_gov > /home/cabnet/gov_pre_v6_3_0_edxeix_prelive_$(date +%Y%m%d_%H%M%S).sql
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_3_0_receipt_only_edxeix_block.sql
```

Verify receipt-only bookings are blocked:

```bash
mysql cabnet_gov -e "
SELECT id, source_system, order_reference, never_submit_live, edxeix_ready, live_submit_block_reason
FROM normalized_bookings
WHERE source_system='bolt_mail' OR order_reference LIKE 'mail:%'
ORDER BY id DESC
LIMIT 20;
"
```

Run pre-live audit:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php --future-hours=24 --past-minutes=60 --limit=50 --json
```

Only possible candidates:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php --future-hours=24 --limit=50 --only-candidates --json
```

## Expected result

- Receipt-only `bolt_mail` bookings are blocked from EDXEIX.
- No `submission_jobs` are created.
- No `submission_attempts` are created.
- Pre-live audit identifies whether there are any real future Bolt API bookings ready for one-shot analysis.

## Still not enabled by this patch

This patch does **not** connect the EDXEIX session.
This patch does **not** submit anything live.
This patch does **not** create submission jobs.
This patch does **not** enable any automatic EDXEIX submit worker.

## Next live-submit rehearsal step

When a real eligible future Bolt API booking exists:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=BOOKING_ID --analyze-only
```

Then, only if the payload is correct and blockers are expected, set one-shot lock:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php --booking-id=BOOKING_ID --by=Andreas
```

Live submission still requires the exact confirmation phrase and session readiness.
