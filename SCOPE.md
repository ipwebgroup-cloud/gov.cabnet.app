# Scope — gov.cabnet.app Bolt → EDXEIX Bridge

## In scope

- Bolt/pre-ride email parsing.
- EDXEIX payload preview.
- Future-only readiness checks.
- Mapping checks for lessor, driver, vehicle, and starting point.
- Admin Excluded vehicle blocking.
- Read-only diagnostics and supervised readiness packets.

## Out of scope unless explicitly approved

- Unattended live EDXEIX submission.
- Cron/live workers for EDXEIX.
- Submitting historical, terminal, cancelled, expired, invalid, or past trips.
- Storing secrets or raw email bodies in Git or diagnostics.

## Current phase

v3.2.27 adds a read-only pre-ride one-shot readiness packet. The next step can be a supervised one-shot transport trace only after Andreas explicitly approves it.
