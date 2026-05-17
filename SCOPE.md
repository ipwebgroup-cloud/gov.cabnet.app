# gov.cabnet.app Scope — ASAP Automation Track

## Included through v3.2.29

- Pre-ride Maildir readiness watch.
- Future-only pre-ride candidate parsing.
- Diagnostics-only HTML fallback parser.
- Sanitized metadata capture.
- One-shot readiness packet.
- Read-only transport rehearsal packet.

## Still excluded

- Unattended EDXEIX live submit.
- Cron-based submit worker.
- Automatic retries.
- Live transport without explicit one-candidate approval.
- Any submission of historical/cancelled/terminal/past/receipt-only rows.

## Current ASAP next gate

The project can proceed to a supervised one-shot transport trace only after explicit approval for one real eligible future pre-ride candidate.
