# gov.cabnet.app v5.2.1 — Driver Receipt Remove Estimated Wording

## Changed files

- `gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `docs/BOLT_DRIVER_RECEIPT_NO_ESTIMATED_WORDING_V5_2_1.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

`gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`

to:

`/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
```

## Safety

This changes only the HTML receipt wording. It does not enable live submit, call Bolt, call EDXEIX, create jobs, create attempts, create bookings, or alter dry-run evidence.
