# Scope — gov.cabnet.app Bolt → EDXEIX Bridge

## ASAP automation track

The current ASAP track is moving from server-side pre-ride candidate readiness toward a safe EDXEIX submission model.

v3.2.34 scope:

- Browser create-form proof from the logged-in EDXEIX session.
- Validate form/token visibility without exposing secrets.
- Confirm real form fields before browser-assisted automation design.

Out of scope:

- Unattended submit.
- Cron.
- AADE/myDATA changes.
- V0 production changes.
- Raw credentials/cookies/token capture.
- Server-side retry for manually closed candidates.
