# gov.cabnet.app v5.3 — Official PDF Receipt Attachment Patch

## Changed files

- `gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf`
- `gov.cabnet.app_config_examples/official_receipt_attachment.example.php`
- `docs/BOLT_DRIVER_OFFICIAL_PDF_RECEIPT_ATTACHMENT_V5_3.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

- `gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php` → `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf` → `/home/cabnet/gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf`

## SQL

No SQL required.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
ls -l /home/cabnet/gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf
```

## Safety

No live submit is enabled. No EDXEIX calls, jobs, attempts, Bolt calls, booking creation, or evidence creation are performed by this patch.
