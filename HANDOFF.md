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
- Use diagnostics, visibility, readiness, evidence capture, and preflight review only.

## Current confirmed state as of 2026-04-26

- Ops console is guarded under `/ops/`.
- EDXEIX session capture works and session readiness has previously shown ready.
- Manual Cookie/CSRF entry was removed from `/ops/edxeix-session.php`.
- Live HTTP transport remains intentionally blocked.
- Bolt API Visibility Diagnostic v1.1 works and records private sanitized timeline snapshots.
- Bolt Dev Accelerator v1.2 was uploaded, syntax-checked, and committed.
- Screenshots confirmed:
  - `/ops/dev-accelerator.php` loads.
  - `/ops/dev-accelerator.php?format=json` returns valid JSON.
  - `/ops/readiness.php` shows `READY_FOR_REAL_BOLT_FUTURE_TEST`.
  - `/ops/future-test.php` shows the system is clean and waiting for a real future Bolt ride.
  - `/ops/bolt-api-visibility.php` loads.
- Latest visible readiness indicators:
  - dry-run enabled: yes
  - Bolt config present: yes
  - EDXEIX config present: yes
  - mapped drivers: 1/2
  - mapped vehicles: 2/15
  - real future candidates: 0
  - local submission jobs: 0
  - LAB rows/jobs: 0
  - live attempts indicated: 0

## This patch

Bolt Evidence Bundle v1.3 adds:

- `/ops/evidence-bundle.php`
- read-only session evidence summary
- readiness passport
- sanitized Bolt visibility timeline summary
- stage coverage for accepted/assigned, pickup/waiting, trip started, completed
- watch match summary for driver, vehicle, and optional order fragment
- copy/paste recap for faster chat/debugging
- JSON output at `/ops/evidence-bundle.php?format=json`

The Evidence Bundle does not call Bolt, does not call EDXEIX, does not stage jobs, does not update mappings, and does not write database rows.

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

Then open `/ops/evidence-bundle.php` to review the full sanitized session report.

Only if a real future candidate appears, open `/bolt_edxeix_preflight.php?limit=30` for preflight preview. Stop before live submission.
