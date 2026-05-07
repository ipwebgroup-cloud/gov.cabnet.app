# gov.cabnet.app Bolt → EDXEIX Bridge — Handoff after v4.5

Current state: safe automated dry-run mode with optional driver email copy layer.

- Mail intake cron is active.
- Auto dry-run evidence cron is active.
- Live EDXEIX submit remains OFF.
- `app.dry_run = true`.
- `edxeix.live_submit_enabled = false`.
- Canonical `edxeix.future_start_guard_minutes = 2`.
- v4.4.1 raw preflight guard alignment was validated: raw JSON now shows guard `2`.

## Recent live test result

A real Bolt email was imported successfully into `bolt_mail_intake`:

- latest observed real row: `id=13`
- `safety_status=blocked_past`
- `linked_booking_id=NULL`
- `submission_jobs=0`
- `submission_attempts=0`

This confirmed the mailbox/import chain and the safety block for too-late/past rides.

## v4.5 feature

Adds optional driver email copies for newly imported real Bolt pre-ride emails.

Flow:

```text
Bolt Ride details email
→ bolt-bridge Maildir
→ import_bolt_mail.php
→ bolt_mail_intake
→ driver email copy if enabled and mapped
→ bolt_mail_driver_notifications audit row
```

New SQL table:

- `bolt_mail_driver_notifications`

New dashboard:

- `/ops/mail-driver-notifications.php?key=INTERNAL_API_KEY`

New service:

- `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`

## Safety

v4.5 does not:

- create `submission_jobs`
- create `submission_attempts`
- POST to EDXEIX
- enable live submit
- send synthetic/test emails to drivers

Driver notifications require server-only config:

- `/home/cabnet/gov.cabnet.app_config/config.php`
- `mail.driver_notifications.enabled = true`
- real driver email mappings by driver name and/or vehicle plate

Do not paste real driver emails or ops keys into chat.
