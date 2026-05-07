# gov.cabnet.app — v5.6 AADE/myDATA Official Receipt Payload Builder

## Purpose

Adds a manual-only AADE/myDATA SendInvoices payload builder for one selected `normalized_bookings` row, usually a `source='bolt_mail'` booking.

This phase prepares and previews the XML payload for official AADE/myDATA receipt issuance. It does not automatically issue receipts and does not email receipt PDFs.

## Safety contract

- No automatic `SendInvoices` call.
- No cron added.
- No driver receipt email enabled.
- No EDXEIX call.
- No `submission_jobs` or `submission_attempts` creation.
- Raw AADE responses are not printed.
- Actual SendInvoices is blocked unless `receipts.aade_mydata.allow_send_invoices=true` and the exact manual confirmation phrase is supplied.

## New files

- `gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php`
- `gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php`
- `public_html/gov.cabnet.app/ops/aade-receipt-payload.php`
- `gov.cabnet.app_config_examples/aade_mydata_send_invoices.example.php`

## Preview command

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID
```

Show XML for accountant review:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID --show-xml
```

Record prepared audit metadata without sending:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID --record-prepared --by=Andreas
```

## Manual send guard

The command supports a manual send mode, but it remains blocked by default:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID --send --confirm='I UNDERSTAND SEND AADE MYDATA PRODUCTION RECEIPT' --by=Andreas
```

This will still block until server-only config contains:

```php
'allow_send_invoices' => true,
```

Do not enable this until accountant review confirms document type, payment method, VAT category, and income classification.

## Payload defaults requiring accountant review

- `invoice_type`: `11.2`
- `vat_category`: `2` for 13% VAT
- `payment_method_type`: `1`
- `income_classification_type`: `E3_561_003`
- `income_classification_category`: `category1_3`
- `series`: `BOLT`
- `aa_prefix`: `BOLT-`

These are configurable server-side and should be confirmed before the first production SendInvoices call.

## Receipt emails

Keep:

```php
'receipt_copy_enabled' => false,
'receipt_pdf_mode' => 'aade_mydata',
```

Driver receipt emails must stay OFF until AADE issuance succeeds and official MARK/UID/QR metadata is stored.
