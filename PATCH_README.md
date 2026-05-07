# gov.cabnet.app v5.4 - Dynamic Driver Receipt PDF Generator

## What changed

The second driver receipt email now attaches a dynamically generated ride-specific PDF instead of a fixed/static PDF.

The PDF includes the parsed Bolt ride details, VAT/TAX 13% breakdown, total, LUX LIMO branding/stamp where available, and a bridge verification QR/hash block.

## Files included

```text
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
gov.cabnet.app_config_examples/generated_receipt_pdf.example.php
docs/BOLT_DYNAMIC_DRIVER_RECEIPT_PDF_V5_4.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload path

```text
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
→ /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
```

## SQL

No SQL required.

## Config

Set under `mail.driver_notifications`:

```php
'receipt_copy_enabled' => true,
'receipt_pdf_mode' => 'generated',
'receipt_pdf_attachment_required' => true,
'receipt_vat_rate_percent' => 13,
'generated_receipt_pdf_filename_prefix' => 'lux-limo-transfer-receipt',
'receipt_logo_path' => '/home/cabnet/public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg',
'receipt_stamp_path' => '/home/cabnet/public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg',
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
```

## Safety

No live EDXEIX submit is enabled. No Bolt/EDXEIX calls, jobs, attempts, booking changes, or dry-run evidence changes are performed by this patch.
