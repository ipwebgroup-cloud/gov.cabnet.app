# gov.cabnet.app — v4.5.1 Bolt Driver Directory Email Sync

Purpose: remove the need to manually map every vehicle plate or driver name to an email address in `config.php`.

## Production behavior

The driver email copy layer now resolves the recipient from the local Bolt driver directory:

```text
Bolt getDrivers API
→ mapping_drivers.driver_email
→ parsed Bolt pre-ride email driver_name / vehicle_plate
→ driver notification email copy
→ bolt_mail_driver_notifications audit row
```

If a new pre-ride email arrives and no recipient is found, the notification service can run a safe Bolt reference refresh once and retry the lookup. This refresh only updates local mapping tables. It does not call EDXEIX, create `submission_jobs`, create `submission_attempts`, or submit live.

## SQL

Run:

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_07_bolt_driver_directory_email_columns.sql
```

This adds these columns to `mapping_drivers` if missing:

```text
driver_identifier
individual_identifier
driver_email
```

## Sync command

Run once after upload and SQL:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php --hours=720
```

Optional cron, every 15 minutes:

```cron
*/15 * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php --hours=720 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_driver_directory_sync.log 2>&1
```

## Config

Do not manually list all drivers. Use the driver directory mode:

```php
'mail' => [
    'bolt_bridge_maildir' => '/home/cabnet/mail/gov.cabnet.app/bolt-bridge',
    'driver_notifications' => [
        'enabled' => true,
        'from_email' => 'bolt-bridge@gov.cabnet.app',
        'from_name' => 'Cabnet Bolt Bridge',
        'reply_to' => 'bolt-bridge@gov.cabnet.app',
        'bcc' => '',
        'subject_prefix' => 'Bolt pre-ride details',
        'resolve_from_bolt_driver_directory' => true,
        'sync_reference_on_miss' => true,
        'sync_reference_hours_back' => 720,
        'manual_driver_emails' => [],
        'manual_vehicle_plate_emails' => [],
    ],
],
```

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
php -l /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-driver-notifications.php
```

Check stored driver directory email coverage:

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" -e "SELECT id,external_driver_name,driver_email,active_vehicle_plate,last_seen_at FROM mapping_drivers WHERE driver_email IS NOT NULL AND driver_email <> '' ORDER BY last_seen_at DESC LIMIT 20;"
```

Open:

```text
https://gov.cabnet.app/ops/mail-driver-notifications.php?key=INTERNAL_API_KEY
```

## Safety

This patch does not enable EDXEIX live submission. It does not create `submission_jobs` or `submission_attempts`.
