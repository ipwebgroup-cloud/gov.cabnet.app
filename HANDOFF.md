# gov.cabnet.app HANDOFF — v5.6

Current state:

- Bolt mail intake is running.
- Driver pre-ride copy by driver identity is validated.
- Generated/non-AADE receipt emails are disabled.
- AADE/myDATA production connectivity is validated with HTTP 200.
- AADE response excerpts are suppressed.
- `receipt_copy_enabled=false`.
- `receipt_pdf_mode=aade_mydata`.
- EDXEIX live submit remains guarded/session-disconnected.

v5.6 added a manual-only AADE/myDATA receipt payload builder:

- `gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php`
- `gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php`
- `public_html/gov.cabnet.app/ops/aade-receipt-payload.php`

The CLI can preview XML from one booking and record a prepared audit row. Actual `SendInvoices` remains blocked unless server-only config explicitly enables `allow_send_invoices=true` and the exact confirmation phrase is supplied.

Before first production SendInvoices, confirm with accountant:

- invoice type, likely ΑΠΥ / `11.2`
- VAT category for 13%, default `2`
- payment method type
- income classification type/category
- series and AA numbering strategy

Never paste AADE credentials or raw AADE response bodies into chat.
