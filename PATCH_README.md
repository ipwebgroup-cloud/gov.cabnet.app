# gov.cabnet.app v6.2.9 Patch — Mail Receipt Duplicate Guard

## Upload paths

Upload/replace:

```text
/home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
/home/cabnet/docs/V6_2_9_MAIL_RECEIPT_DUPLICATE_GUARD.md
/home/cabnet/PATCH_README.md
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
```

## SQL

No SQL migration required.

## Validate

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
```

## Dry-run

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --dry-run --minutes=240 --limit=25 --json
```

## Live run

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --minutes=240 --limit=25 --json
```

## Cron

Keep the v6.2.8/v6.2.9 cron line after this file is replaced:

```cron
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --minutes=240 --limit=25 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_receipts.log 2>&1
```

## Expected behavior

- Real new intake after pickup: booking created/linked, AADE issued, driver emailed.
- Duplicate near-identical intake after a receipt already exists: skipped as `duplicate_logical_trip_suppressed`.
- EDXEIX queues remain zero.

## Safety checks

```bash
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs;"
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
```
