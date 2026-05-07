Continue gov.cabnet.app Bolt → EDXEIX bridge from v5.7.

The project now has AADE/myDATA production connectivity and a manual SendInvoices payload builder. v5.7 hardens the first controlled SendInvoices gate with config enablement, exact confirmation phrase, duplicate checks by booking and XML hash, and suppressed raw AADE output.

Keep receipt_copy_enabled=false and receipt_pdf_mode=aade_mydata until official AADE issuance succeeds and official PDF/receipt attachment handling is complete. Keep live EDXEIX guarded/session-disconnected.
