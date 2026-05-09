# EDXEIX Readiness Report v6.6.1

## Purpose

`edxeix_readiness_report.php` is a read-only CLI report for EDXEIX pre-live readiness.

v6.6.1 corrects the source policy:

- EDXEIX submission data source is strictly pre-ride Bolt email intake / mail-derived normalized bookings.
- Bolt API pickup/finalized data is not an EDXEIX submission source.
- AADE invoice issuing remains strictly limited to the Bolt API pickup timestamp worker.

## Safety

The report:

- Does not call EDXEIX.
- Does not issue AADE receipts.
- Does not create `submission_jobs` rows.
- Does not create `submission_attempts` rows.
- Does not print cookies, CSRF tokens, API keys, or private config values.

## Correct Source Split

```text
Pre-ride Bolt email
→ bolt_mail_intake
→ mail-derived normalized local preflight booking
→ EDXEIX readiness / browser-fill / future one-shot live submit
```

```text
Bolt API pickup timestamp
→ bolt_pickup_receipt_worker.php
→ AADE invoice issue
```

## Commands

Syntax check:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
```

Standard JSON report:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json
```

Only ready mail-derived bookings:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
```

Diagnostic view including non-mail rows:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --include-non-mail --future-hours=168 --limit=100 --json
```

## Expected Safe Output

```json
{
  "ok": true,
  "version": "v6.6.1",
  "source_policy": {
    "edxeix_submission_source": "pre_ride_bolt_email_only",
    "edxeix_uses_bolt_api_as_source": false,
    "aade_invoice_source": "bolt_api_pickup_timestamp_worker_only",
    "pre_ride_email_may_issue_aade": false
  },
  "safety": {
    "does_not_call_edxeix": true,
    "does_not_issue_aade_receipts": true,
    "does_not_create_submission_jobs": true,
    "does_not_create_submission_attempts": true
  },
  "queue_counts": {
    "queues_unchanged": true
  }
}
```

## Readiness Meaning

A booking is `preflight_ready=true` only when it is:

- mail-derived from pre-ride Bolt email,
- future enough to satisfy the configured guard,
- not terminal/past/cancelled/finished,
- not receipt-only or late AADE recovery,
- not flagged `never_submit_live`,
- not lab/test,
- mapped to EDXEIX driver, vehicle, and starting point,
- not a duplicate.

`preflight_ready=true` does not mean live submit is enabled. It only means the booking is ready for manual/analyze-only review before any future one-shot live activation.

## Commit Scope

No SQL changes.
No production behavior changes.
No live EDXEIX activation.
