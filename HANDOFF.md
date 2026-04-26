# gov.cabnet.app — Handoff

## Project

Bolt Fleet API → normalized local bookings → EDXEIX preflight/readiness workflow.

## Safety posture

The project remains at a pre-live blocked baseline.

- Do not enable live EDXEIX submission.
- Keep `live_submit_enabled` false.
- Keep `http_submit_enabled` false.
- Do not submit historical, cancelled, terminal, expired, invalid, or past Bolt trips.
- Do not expose real secrets, cookies, CSRF values, API keys, DB passwords, or raw session files.

## Current confirmed state

- Readiness is clean: `READY_FOR_REAL_BOLT_FUTURE_TEST`.
- Real future Bolt candidate count is currently `0`.
- Existing historical Bolt rows are visible and correctly blocked as terminal/past.
- Filippos + EMX6874 mappings are available for the first real test.
- Live EDXEIX submission remains disabled.
- Dev Accelerator, Evidence Bundle, Evidence Report, Test Session Control, and Preflight Review Assistant are available.

## Most recent patch

Bolt Ops UI Polish v1.7 adds a shared EDXEIX-style CSS presentation layer for:

- `/ops/test-session.php`
- `/ops/preflight-review.php`

New CSS file:

- `/assets/css/gov-ops-edxeix.css`

This is presentation-only. It does not change workflow logic, queue logic, mapping logic, preflight logic, or live submit behavior.

## Known mappings

- Filippos Giannakopoulos
  - Bolt UUID: `57256761-d21b-4940-a3ca-bdcec5ef6af1`
  - EDXEIX driver ID: `17585`
- EMX6874
  - EDXEIX vehicle ID: `13799`
- EHA2545
  - EDXEIX vehicle ID: `5949`

Leave Georgios Zachariou unmapped until his exact EDXEIX driver ID is independently confirmed.

## Next safest step

If a real Bolt ride is available, use `/ops/test-session.php` and capture the four stages. If no real ride is available, continue applying the v1.7 visual theme to the remaining ops pages in small batches.
