# v6.2.8 — Bolt Mail AADE Receipt Worker

## Purpose

Stabilize live driver receipt delivery by using the Bolt pre-ride email as the primary receipt source instead of waiting for Bolt Fleet API order finish data.

The live ride for intake 26 proved the emergency-safe path:

- create/link a local normalized booking from `bolt_mail_intake`
- use the first number from `estimated_price_raw` as the AADE receipt amount
- wait until `parsed_pickup_at`
- issue AADE/myDATA through the existing duplicate-protected issuer
- email the driver through the existing driver notification service
- keep EDXEIX untouched

## New CLI

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --minutes=240 --limit=25 --json
```

Dry-run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --dry-run --minutes=240 --limit=25 --json
```

## Recommended cron

Add this cron line after upload and validation:

```cron
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --minutes=240 --limit=25 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_receipts.log 2>&1
```

Keep the current Bolt API sync/audit workers read-only. Do not enable EDXEIX live submit.

## Safety guarantees

The new worker:

- does not call EDXEIX
- does not create `submission_jobs`
- does not create `submission_attempts`
- does not store Bolt API payloads
- does not print credentials or raw mail bodies
- uses existing AADE duplicate gates
- uses existing driver email delivery
- waits for the existing `AadeReceiptAutoIssuer` pickup-time gate

## Why this is needed

The direct live Bolt API audit returned no `active_picked_up_before_finish` rows during the observed live window. Therefore, relying only on `getFleetOrders` risks issuing the driver receipt too late. The Bolt pre-ride email contains enough safe data to prepare the receipt booking before pickup and issue exactly at/after pickup time.

## Passenger/customer name fix included

The patch also carries forward the v6.2.6 receipt name fix:

- prefer `bolt_mail_intake.customer_name`
- ignore generic API placeholders like `Bolt Passenger`
- include passenger name in AADE line comments when available
- display the real passenger name in driver PDF/email receipt copies

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
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

Expected:

```text
submission_jobs = 0
submission_attempts = 0
```
