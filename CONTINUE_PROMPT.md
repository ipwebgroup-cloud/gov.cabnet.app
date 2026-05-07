Continue the gov.cabnet.app Bolt → EDXEIX bridge project from v5.6.

Key state:
- AADE/myDATA production connectivity works and is privacy-hardened.
- Receipt emails are disabled until official AADE issuance succeeds.
- v5.6 added manual AADE SendInvoices XML payload preview from one `normalized_bookings` row.
- Actual SendInvoices remains blocked by config `allow_send_invoices=false` and a required exact confirmation phrase.
- EDXEIX live submit remains guarded/session-disconnected.

Next safest work:
1. Preview AADE payload for a known `source='bolt_mail'` booking.
2. Review payload with accountant/AADE requirements.
3. Confirm invoice type, payment method, income classification, series/AA strategy.
4. Only after explicit approval, enable one controlled AADE SendInvoices test.
5. Do not enable automatic receipt emails until AADE returns official MARK/UID/QR data.
