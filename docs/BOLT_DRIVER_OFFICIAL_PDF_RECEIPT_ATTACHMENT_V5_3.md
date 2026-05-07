# v5.3 — Driver Official PDF Receipt Attachment

This patch changes the second driver receipt email so the official receipt is sent as a PDF attachment.

## Purpose

The prior HTML receipt was useful for previewing VAT and ride details, but a legitimate receipt must preserve the official document layout, tax fields, MARK/QR/code area, and company marking. v5.3 sends a short HTML email body and attaches the configured official PDF receipt.

## Installed default attachment path

`/home/cabnet/gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf`

The included PDF is the uploaded official receipt sample. For production, replace or generate this PDF from the official invoicing platform before sending it for a real transfer. Do not rely on manually recreated HTML as the legal receipt.

## Safety

This patch does not enable live EDXEIX submit, call EDXEIX, call Bolt, create submission jobs, create submission attempts, change normalized bookings, change dry-run evidence, or change live-submit gates.

## Receipt behavior

- Normal driver copy email still sends first.
- Second receipt email sends as multipart/mixed.
- Email body is HTML and base64 wrapped.
- PDF attachment is application/pdf and base64 wrapped.
- The PDF preserves the official receipt layout and embedded QR/code if present in the PDF.
- If the configured PDF is missing and `receipt_pdf_attachment_required=true`, the receipt email is skipped instead of sending an unofficial HTML-only receipt.

## Config keys

Add under `mail.driver_notifications` if you want to override defaults:

```php
'receipt_copy_enabled' => true,
'receipt_pdf_attachment_enabled' => true,
'receipt_pdf_attachment_required' => true,
'receipt_pdf_attachment_path' => '/home/cabnet/gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf',
'receipt_pdf_attachment_filename' => 'lux-limo-receipt.pdf',
```
