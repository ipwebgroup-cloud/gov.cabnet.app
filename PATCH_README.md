# gov.cabnet.app v5.2.2 Driver Receipt Email Transport Fix

## What changed

The HTML driver receipt email is now base64-encoded and line-wrapped before sending. This fixes Exim/Gmail delivery rejection errors like:

`message has lines too long for transport (received 9176, limit 2048)`

## Files included

- `gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `docs/BOLT_DRIVER_RECEIPT_EMAIL_TRANSPORT_V5_2_2.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload path

`gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
→ `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
```

## Safety

No EDXEIX submit, no Bolt call, no EDXEIX call, no jobs, no attempts, no booking/evidence changes.
