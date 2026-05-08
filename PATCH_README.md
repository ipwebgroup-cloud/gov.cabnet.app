# gov.cabnet.app v6.2.8 Patch — Stabilized Bolt Mail AADE Receipt Worker

## What changed

- Added `bolt_mail_receipt_worker.php` to issue AADE receipts from Bolt pre-ride email intake at/after pickup time.
- The worker creates/links a receipt-only `normalized_bookings` row when the intake is not yet linked.
- It uses the first number from Bolt `estimated_price_raw` as the receipt amount.
- It uses the existing AADE duplicate-protected issuer and existing driver PDF/email delivery.
- It keeps EDXEIX untouched and does not create submission queues.
- Included receipt passenger-name fixes from v6.2.6.

## Files included

```text
gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
docs/V6_2_8_BOLT_MAIL_RECEIPT_WORKER.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
/home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
/home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
/home/cabnet/docs/V6_2_8_BOLT_MAIL_RECEIPT_WORKER.md
/home/cabnet/PATCH_README.md
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
```

## SQL

No SQL required.

## Validation

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

Recommended cron:

```cron
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --minutes=240 --limit=25 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_receipts.log 2>&1
```

Safety verification:

```bash
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs;"
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
```

Expected:

```text
submission_jobs = 0
submission_attempts = 0
```

## Git commit title

```text
v6.2.8 Stabilize Bolt mail AADE receipt worker
```

## Git commit description

```text
- Add dedicated Bolt mail AADE receipt worker for pickup-time driver receipt delivery.
- Create/link receipt-only normalized bookings from parsed Bolt pre-ride email intake.
- Use first estimated price value from Bolt email as official receipt amount.
- Reuse existing AADE duplicate gates and driver receipt PDF/email delivery.
- Keep EDXEIX live submission disabled and queues untouched.
- Carry forward passenger-name fixes for AADE payloads and driver receipt PDFs.
```
