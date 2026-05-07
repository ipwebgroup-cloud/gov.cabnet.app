# v5.7 — AADE/myDATA First Controlled SendInvoices Gate

This patch hardens the manual SendInvoices path before the first official AADE/myDATA receipt transmission.

## Scope

- Keeps AADE SendInvoices manual-only.
- Requires `receipts.aade_mydata.allow_send_invoices=true` before any send.
- Requires the exact server-side confirmation phrase.
- Blocks duplicate official issuance by `normalized_booking_id` and XML payload hash.
- Suppresses raw AADE response output.
- Records only response metadata, MARK/UID/QR fields when available, hashes, and status in `receipt_issuance_attempts`.

## Safety

The patch does not send anything on install. It does not email receipts, call EDXEIX, create `submission_jobs`, create `submission_attempts`, or enable generated receipt fallback.

## First-send flow

1. Confirm accountant/authority values:
   - invoice type
   - VAT category/rate
   - payment method
   - income classification
   - series and numbering
2. Preview the payload:
   `/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID`
3. Optionally record prepared:
   `/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID --record-prepared --by=Andreas`
4. Enable `allow_send_invoices=true` in server config only when ready.
5. Run the manual send command with the exact confirmation phrase.

Receipt emails must remain disabled until an official successful AADE issuance is confirmed and official PDF/receipt attachment handling is implemented.
