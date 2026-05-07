# v4.5 Bolt Mail Driver Notification Patch

## What changed

Adds a safe driver email copy layer for newly imported Bolt pre-ride emails.

When enabled in server-only config, each newly inserted real Bolt mail intake row sends one plain-text driver copy based on configured driver name or vehicle plate email mappings.

## Files included

```text
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
gov.cabnet.app_app/src/Mail/BoltPreRideImporter.php
gov.cabnet.app_app/cli/import_bolt_mail.php
public_html/gov.cabnet.app/ops/mail-intake.php
public_html/gov.cabnet.app/ops/mail-driver-notifications.php
gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_notifications.sql
gov.cabnet.app_config/config.php.example
gov.cabnet.app_config_examples/driver_notifications.example.php
docs/BOLT_MAIL_DRIVER_NOTIFICATIONS_V4_5.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL to run

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_notifications.sql
```

## Server-only config to add

Merge the `mail.driver_notifications` section into:

```text
/home/cabnet/gov.cabnet.app_config/config.php
```

Keep real driver emails server-side only. Do not commit or paste them into chat.

## Safety

This patch does not create EDXEIX jobs, attempts, or POSTs. Live EDXEIX submission remains off.
