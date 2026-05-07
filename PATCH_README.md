# gov.cabnet.app v4.5.3 — Driver Copy Format Tweaks

## Files included

```text
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
docs/BOLT_DRIVER_COPY_FORMAT_V4_5_3.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
→ /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
```

## Expected result

For new driver copies only:

- Estimated end time = estimated pick-up time + 30 minutes.
- Estimated price range displays only the first value.
- No EDXEIX jobs, attempts, or POSTs are created by this patch.
