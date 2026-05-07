# v5.4 Dynamic Driver Receipt PDF Generator

This patch changes the second driver receipt email from a static attached PDF to a per-ride generated PDF attachment.

## Behavior

For each successful real Bolt pre-ride driver notification:

1. The normal driver copy email is sent.
2. A second receipt email is sent.
3. The receipt email attaches a generated ride-specific PDF.
4. The PDF is populated from the parsed Bolt mail row.

The generated PDF includes:

- LUX LIMO / MYKONOS CAB header.
- Passenger, driver, vehicle, pickup, drop-off, pick-up time, and end time.
- End time calculated as pick-up time plus 30 minutes.
- Price normalized to the first value when Bolt provides a range.
- Net amount, VAT/TAX at 13%, and total.
- Company logo/stamp images when available on the server.
- Bridge verification QR payload and hash block.

## Legal boundary

This generated PDF is a bridge-generated receipt/pro-forma. It is not an official AADE/myDATA receipt unless the data is separately issued by an official invoicing platform and official MARK/QR values are returned.

## Config

Add these options under `mail.driver_notifications` in `/home/cabnet/gov.cabnet.app_config/config.php`:

```php
'receipt_copy_enabled' => true,
'receipt_pdf_mode' => 'generated',
'receipt_pdf_attachment_required' => true,
'receipt_vat_rate_percent' => 13,
'generated_receipt_pdf_filename_prefix' => 'lux-limo-transfer-receipt',
'receipt_logo_path' => '/home/cabnet/public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg',
'receipt_stamp_path' => '/home/cabnet/public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg',
```

## Safety

This patch does not enable live EDXEIX submission and does not call Bolt or EDXEIX. It does not create submission jobs or submission attempts. It only changes receipt email attachment generation.
