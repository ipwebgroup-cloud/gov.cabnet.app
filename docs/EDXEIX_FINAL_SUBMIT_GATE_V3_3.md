# EDXEIX Final Submission Gate v3.3

Adds `/ops/edxeix-final-submit-gate.php`.

## Purpose

Provide a final read-only go/no-go gate before any future live-submit handler.

It combines:

- EDXEIX session freshness
- EDXEIX session CSRF/token presence
- authenticated form URL confirmation
- submit action URL confirmation
- local payload builder availability
- local form contract compatibility
- recent normalized booking eligibility
- historical/terminal/future-guard blockers

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

## Expected current result

`FINAL_SUBMIT_GATE_PREPARED_NO_ELIGIBLE_CANDIDATE`

This means mechanics are ready, but no future-safe candidate exists.
