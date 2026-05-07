# gov.cabnet.app v4.5.1 — Bolt Driver Directory Email Sync

This patch supersedes the manual driver-email mapping approach from v4.5.

## Install

Upload these files to their matching live paths, then run both SQL migrations if v4.5 was not already installed:

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_notifications.sql
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_07_bolt_driver_directory_email_columns.sql
```

Run syntax checks:

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltPreRideImporter.php
php -l /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php
php -l /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-driver-notifications.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-intake.php
```

Run driver directory sync:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php --hours=720
```

## Config

Use the `mail.driver_notifications` block in `gov.cabnet.app_config_examples/driver_notifications.example.php`.

Do not list every driver manually. Leave manual fallback arrays empty unless the Bolt API does not expose a driver email.

## Safety

This patch sends email copies only. It does not create EDXEIX jobs, attempts, or live submissions.
