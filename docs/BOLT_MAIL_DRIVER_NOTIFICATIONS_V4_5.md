# gov.cabnet.app — v4.5 Bolt Mail Driver Notifications

## Purpose

When a new Bolt pre-ride `Ride details` email reaches `bolt-bridge@gov.cabnet.app`, the mail intake importer can now send one immediate driver email copy from our side.

This is an independent notification layer. It does not submit anything to EDXEIX.

## Flow

```text
Bolt Ride details email
→ Gmail forwarding / cPanel mailbox
→ bolt-bridge Maildir
→ import_bolt_mail.php cron
→ bolt_mail_intake row
→ optional driver email copy based on config mapping
→ bolt_mail_driver_notifications audit row
```

## Safety boundary

v4.5 does not:

- create `submission_jobs`
- create `submission_attempts`
- POST to EDXEIX
- enable live EDXEIX submit
- send synthetic/test emails to real drivers

Synthetic/test rows containing markers such as `CABNET TEST`, `DO NOT SUBMIT`, or `SYNTHETIC` are suppressed.

## Configuration

Driver notifications are disabled by default in the example config. Enable only after:

1. the SQL migration has been run
2. real driver emails have been added server-side
3. a test recipient has been verified

Server-only config path:

```text
/home/cabnet/gov.cabnet.app_config/config.php
```

Example top-level config section:

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
        'driver_emails' => [
            'Filippos Giannakopoulos' => 'REPLACE_WITH_DRIVER_EMAIL',
            'Nikolaos Vidakis' => 'REPLACE_WITH_DRIVER_EMAIL',
        ],
        'vehicle_plate_emails' => [
            'EHA2545' => 'REPLACE_WITH_DRIVER_EMAIL',
            'EMX6874' => 'REPLACE_WITH_DRIVER_EMAIL',
        ],
    ],
],
```

Do not commit real driver email addresses.

## Audit dashboard

```text
/ops/mail-driver-notifications.php?key=INTERNAL_API_KEY
```

Shows sent/skipped/failed notification records with masked recipient addresses.

## CLI log behavior

The mail intake cron now prints driver notification counters:

```text
driver_sent=1 driver_skipped=0 driver_failed=0
```

A successful driver copy does not mean EDXEIX was submitted. It is only an email copy.
