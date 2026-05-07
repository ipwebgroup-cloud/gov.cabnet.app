# HANDOFF — gov.cabnet.app Bolt → EDXEIX / AADE bridge after v5.7

Current state:

- Driver pre-ride copy works by Bolt driver identity.
- Receipt emails are disabled until official AADE issuance and official PDF flow are complete.
- AADE production connectivity is confirmed and response excerpts are suppressed.
- AADE payload preview for booking 16 is valid.
- v5.7 adds a stricter first controlled SendInvoices gate.
- Live EDXEIX remains guarded/session-disconnected.

Important safety:

- Do not enable receipt emails until AADE SendInvoices succeeds and official PDF/receipt attachment flow is implemented.
- Do not use generated/static receipt fallback.
- Do not paste AADE credentials or raw AADE responses.
- SendInvoices is manual-only and requires config + exact confirmation phrase.
