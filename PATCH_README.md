# gov.cabnet.app v5.1 — Driver Receipt Copy Patch

## What changed

Adds a second email to the driver after a successful Bolt pre-ride driver copy. The second email is an HTML receipt copy with:

- all key ride details from the original Bolt pre-ride email
- estimated end time formatted with the driver-copy 30-minute rule
- estimated price normalized to the first value only
- VAT/TAX section at 13% included in the total
- LUX LIMO company stamp image
- audit columns for receipt status and VAT values

## Files included

```text
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
public_html/gov.cabnet.app/ops/mail-driver-notifications.php
public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg
gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_receipt_columns.sql
docs/BOLT_DRIVER_RECEIPT_COPY_V5_1.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
→ /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php

public_html/gov.cabnet.app/ops/mail-driver-notifications.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mail-driver-notifications.php

public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg
→ /home/cabnet/public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg

gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_receipt_columns.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_receipt_columns.sql
```

## SQL

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_receipt_columns.sql
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-driver-notifications.php

DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" -e "SHOW COLUMNS FROM bolt_mail_driver_notifications LIKE 'receipt_status';"
mysql "$DB_NAME" -e "SHOW COLUMNS FROM bolt_mail_driver_notifications LIKE 'receipt_vat_amount';"
```

## Expected result

For the next real Bolt pre-ride email:

```text
driver copy email: sent
receipt copy email: sent
bolt_mail_driver_notifications.notification_status = sent
bolt_mail_driver_notifications.receipt_status = sent
submission_jobs = 0
submission_attempts = 0
```

## Safety

This patch does not enable live EDXEIX submit and does not change EDXEIX payloads, jobs, attempts, dry-run evidence, or normalized booking logic.
