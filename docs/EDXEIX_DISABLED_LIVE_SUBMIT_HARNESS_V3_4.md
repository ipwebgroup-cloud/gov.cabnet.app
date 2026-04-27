# EDXEIX Disabled Live Submit Harness / Approval Runbook v3.4

Adds `/ops/edxeix-disabled-live-submit-harness.php`.

## Purpose

Document and preview the future live-submit sequence without implementing or enabling live EDXEIX submission.

## Safety

The page:

- does not call Bolt
- does not call EDXEIX
- does not POST
- reads local config/session metadata and recent normalized bookings only
- does not write database rows or files
- does not stage jobs
- does not update mappings
- does not print cookies, token values, raw session JSON, or passenger payload values
- does not enable live submission

## Approval model

Future live submit must require:
- exactly one eligible future-safe candidate
- fresh EDXEIX session
- valid CSRF/token
- confirmed form/action URLs
- contract-ready payload
- explicit Andreas approval
- POST only, never GET
- one candidate, one attempt, then stop
