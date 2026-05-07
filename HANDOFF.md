# HANDOFF — gov.cabnet.app v5.6.1

Current phase: AADE/myDATA official receipt payload preparation.

Validated before this patch:

- AADE production connectivity works with HTTP 200.
- AADE response excerpts are suppressed.
- `receipt_copy_enabled=false`.
- `receipt_pdf_mode=aade_mydata`.
- v5.6 can build XML for booking 16 and record a `prepared` audit row.

v5.6.1 adds:

- two-decimal JSON amount display
- accountant review checklist output
- config gate output
- updated ops page with gate checks and latest attempts

Keep `allow_send_invoices=false` until accountant confirmation and explicit approval for a controlled SendInvoices test.
