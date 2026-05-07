# v5.8 - Automatic AADE/myDATA Receipt Issuance and Driver Receipt Email

## Purpose

Automatically issue the official AADE/myDATA receipt for the next real Bolt pre-ride order and email the issued receipt PDF to the driver.

## Production flow

```text
Bolt pre-ride email arrives
-> mail intake imports it
-> normal driver copy is sent
-> auto dry-run creates/links normalized_bookings source='bolt_mail'
-> dry-run evidence is recorded or already exists
-> AADE/myDATA SendInvoices is called automatically when enabled
-> MARK / UID / QR URL metadata is stored in receipt_issuance_attempts
-> AADE receipt PDF is generated from official AADE metadata
-> receipt PDF is emailed to the driver
```

## Safety boundaries retained

The user requested automatic mode. The patch enables automatic behavior but keeps the necessary production safety gates:

- only `source='bolt_mail'` normalized bookings
- no synthetic/test rows
- positive price required
- linked mail intake required
- `receipts.mode='aade_mydata'`
- `receipts.aade_mydata.enabled=true`
- `receipts.aade_mydata.allow_send_invoices=true`
- `receipts.aade_mydata.auto_send_invoices=true`
- `receipts.aade_mydata.auto_issue_not_before` is configured to the activation time and `auto_issue_not_before` is set
- `mail.driver_notifications.receipt_pdf_mode='aade_mydata'`
- `mail.driver_notifications.official_receipt_email_enabled=true`
- duplicate protection by booking ID and XML hash
- no generated/static receipt fallback
- no receipt email unless AADE issuance succeeds
- no EDXEIX call
- no submission_jobs
- no submission_attempts

## Operational note

The automatic issuance is performed by the existing every-minute cron:

```text
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --limit=50
```

No new cron is required.

## Config required

Add these to the private server config only:

```php
'mail' => [
    'driver_notifications' => [
        'receipt_copy_enabled' => false,
        'receipt_pdf_mode' => 'aade_mydata',
        'official_receipt_email_enabled' => true,
    ],
],

'receipts' => [
    'mode' => 'aade_mydata',
    'aade_mydata' => [
        'enabled' => true,
        'allow_send_invoices' => true,
        'auto_send_invoices' => true,
        'auto_issue_not_before' => '2026-05-07 23:59:00 Europe/Athens',
    ],
],
```

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailAutoDryRunService.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
php -l /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php
```

Preview cron behavior:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --limit=5 --json
```

Check AADE audit:

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" -e "SELECT id,provider_status,http_status,normalized_booking_id,total_amount,mark,uid,qr_url,official_pdf_path,created_by,created_at FROM receipt_issuance_attempts ORDER BY id DESC LIMIT 10;"
```

Check driver receipt email audit:

```bash
mysql "$DB_NAME" -e "SELECT id,intake_id,driver_name,vehicle_plate,notification_status,receipt_status,receipt_sent_at,receipt_error_message FROM bolt_mail_driver_notifications ORDER BY id DESC LIMIT 10;"
```

## Rollback

Set these values in config:

```php
'receipts' => [
    'aade_mydata' => [
        'auto_send_invoices' => false,
    ],
],

'mail' => [
    'driver_notifications' => [
        'official_receipt_email_enabled' => false,
    ],
],
```
