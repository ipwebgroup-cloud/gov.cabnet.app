Continue the gov.cabnet.app Bolt → EDXEIX bridge from v5.6.1.

State:
- AADE/myDATA production connectivity is confirmed.
- AADE response privacy hardening is active.
- Generated receipt fallback is off.
- Receipt emails are disabled until AADE issuance succeeds.
- v5.6 builds AADE SendInvoices XML from selected bolt_mail bookings.
- v5.6.1 polished output and added first-send gates/checklists.

Next safest step:
- Review AADE payload values with accountant/authority.
- Do not enable `allow_send_invoices` until confirmed.
- Do not send receipt emails until AADE returns official MARK/UID/QR metadata.
- Keep EDXEIX guarded/session-disconnected unless explicitly approved.
