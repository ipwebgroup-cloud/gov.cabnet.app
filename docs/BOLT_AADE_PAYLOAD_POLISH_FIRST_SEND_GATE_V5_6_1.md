# gov.cabnet.app — v5.6.1 AADE Payload Polish + First Send Gate

## Purpose

v5.6.1 polishes the AADE/myDATA receipt payload preview and keeps the first `SendInvoices` action behind explicit manual gates.

## Changes

- Formats JSON payload summary amounts as two-decimal strings.
- Keeps the generated XML amounts unchanged and two-decimal safe.
- Adds net + VAT = gross validation.
- Adds accountant review checklist output for:
  - document type
  - VAT category / 13% rate
  - payment method type
  - income classification
  - series / AA numbering
  - amount totals
- Adds config gate output showing:
  - `receipts.mode`
  - `receipts.aade_mydata.enabled`
  - `receipts.aade_mydata.environment`
  - `receipts.aade_mydata.allow_send_invoices`
  - driver receipt copy status
  - receipt PDF mode
- Updates the ops page with gate checks and latest AADE receipt attempts.

## Safety

This patch does not:

- send AADE invoices automatically
- email receipts
- call EDXEIX
- create EDXEIX submission jobs
- create EDXEIX submission attempts
- change live-submit gates
- enable generated receipt fallback

## Required current config posture

```php
'receipt_copy_enabled' => false,
'receipt_pdf_mode' => 'aade_mydata',

'receipts' => [
    'mode' => 'aade_mydata',
    'aade_mydata' => [
        'enabled' => true,
        'environment' => 'production',
        'allow_send_invoices' => false,
    ],
],
```

## First SendInvoices remains blocked

`SendInvoices` remains blocked unless:

1. `receipts.aade_mydata.allow_send_invoices = true`
2. the exact configured confirmation phrase is supplied
3. payload validation passes
4. there is no previous `issued` receipt attempt for the same booking

Receipt emails must remain disabled until a successful AADE issuance path stores official MARK/UID/QR metadata.
