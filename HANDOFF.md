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

- Ops console is guarded under `/ops/`.
- EDXEIX session capture works and session readiness has previously shown ready.
- Manual Cookie/CSRF entry was removed from `/ops/edxeix-session.php`.
- Live HTTP transport remains intentionally blocked.
- Bolt API Visibility Diagnostic works and records private sanitized timeline snapshots.
- Dev Accelerator v1.2 is uploaded, syntax-checked, committed, and shows `READY_FOR_REAL_BOLT_FUTURE_TEST`.
- Evidence Bundle v1.3 is uploaded, syntax-checked, committed, and currently shows `WAITING_FOR_EVIDENCE` because no real ride snapshots exist yet for the selected date.
- Evidence Report Export v1.4 adds `/ops/evidence-report.php`, a read-only Markdown/JSON report exporter for the existing sanitized timeline.

## Current observed readiness

- Verdict: `READY_FOR_REAL_BOLT_FUTURE_TEST`
- Real future candidates: `0`
- Driver mappings: `1/2`
- Vehicle mappings: `2/15`
- LAB rows/jobs: `0`
- Local submission jobs: `0`
- Live attempts: `0`
- Live EDXEIX submit: disabled

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

Use `/ops/dev-accelerator.php` during a real future/scheduled Bolt ride with Filippos and EMX6874. Record snapshots after:

1. accepted/assigned
2. pickup/waiting
3. trip started
4. completed

Then open `/ops/evidence-report.php` and paste the generated Markdown report into the chat.
