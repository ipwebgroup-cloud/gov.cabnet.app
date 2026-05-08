# v6.2.9 — Bolt Mail Receipt Duplicate Guard

## Purpose

v6.2.8 proved that the Bolt pre-ride email path can issue AADE receipts at pickup time without waiting for Bolt API finish data.

A live run then showed a critical duplicate risk: two parsed Bolt mail intake rows for the same logical ride can arrive a few minutes apart and both may be eligible after pickup. v6.2.9 adds a guard to prevent this.

## What changed

- `bolt_mail_receipt_worker.php` now uses version `v6.2.9`.
- Adds a process lock: only one worker instance may run at a time.
- Adds `dedupe_suppressed` summary output.
- Before creating/linking/issuing, the worker checks whether another intake already has an issued AADE receipt for the same logical ride:
  - same customer name
  - same driver name
  - same vehicle plate
  - same pickup address
  - same drop-off address
  - same first estimated price amount
  - pickup time within 45 minutes
- If matched, the row is skipped as `duplicate_logical_trip_suppressed`.

## Safety posture

- No EDXEIX calls.
- No `submission_jobs` creation.
- No `submission_attempts` creation.
- No API keys or secrets printed.
- Existing AADE duplicate gates remain active.
- Existing driver PDF/email delivery remains active.

## Important production note

Receipts already issued before this patch are not voided, cancelled, or modified by this patch. If duplicate official receipts were issued, review them with the accountant before taking any AADE/myDATA cancellation/correction action.

## Validation

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
```

Dry-run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --dry-run --minutes=240 --limit=25 --json
```

Live run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --minutes=240 --limit=25 --json
```

Safety checks:

```bash
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs;"
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
```
