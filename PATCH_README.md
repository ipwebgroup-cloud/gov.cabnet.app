# gov.cabnet.app v4.5.2 Patch — Driver Identity Email Resolution

## Upload paths

- `gov.cabnet.app_app/lib/bolt_sync_lib.php` → `/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php`
- `gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php` → `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `public_html/gov.cabnet.app/ops/mail-driver-notifications.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/mail-driver-notifications.php`

Docs/examples:

- `gov.cabnet.app_config/config.php.example`
- `gov.cabnet.app_config_examples/driver_notifications.example.php`
- `docs/BOLT_DRIVER_IDENTITY_EMAIL_RESOLUTION_V4_5_2.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-driver-notifications.php
```

Run driver directory sync again:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php --hours=720
```

Check driver identity/email coverage:

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" -e "SELECT id,external_driver_name,driver_identifier,individual_identifier,driver_email,last_seen_at FROM mapping_drivers WHERE driver_email IS NOT NULL AND driver_email <> '' ORDER BY last_seen_at DESC LIMIT 20;"
```

## Expected result

Driver notifications resolve only by driver identity/name, never by vehicle plate.
