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
- Treat all new tooling as read-only, dry-run, diagnostic, queue visibility, or preflight-only unless Andreas explicitly asks for live-submit work.

## Current confirmed state

- Ops console is guarded under `/ops/`.
- EDXEIX session capture works and session readiness has previously shown ready.
- Manual Cookie/CSRF entry was removed from `/ops/edxeix-session.php`.
- Live HTTP transport remains intentionally blocked.
- Bolt API Visibility Diagnostic v1.0 was uploaded and committed on 2026-04-25.
- Bolt API Visibility Diagnostic v1.1 added local normalized booking visibility.
- Screenshots confirmed the diagnostic page works and records private sanitized timeline snapshots.
- The screenshots showed `orders_seen: 1`, `sanitized_samples: 0`, and watch matches `NO` for Filippos/EMX6874 while no active future test was available.

## Latest patch

Bolt Dev Accelerator v1.2 adds:

```text
/ops/dev-accelerator.php
```

Purpose:

- Speed up the next real future Bolt ride test.
- Keep readiness, capture buttons, auto-watch, JSON status, and verification URLs on one page.
- Avoid jumping between multiple pages while the ride state changes.

Safety:

- Default page load does not call Bolt.
- Optional capture buttons call the existing Bolt visibility diagnostic dry-run path only.
- No EDXEIX submission.
- No job staging.
- No mapping edits.
- No raw Bolt payload printing.

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

Upload Bolt Dev Accelerator v1.2 and open:

```text
https://gov.cabnet.app/ops/dev-accelerator.php
```

Then, during a real future/scheduled Bolt ride with Filippos and EMX6874, use the accelerator capture buttons after:

1. accepted/assigned
2. pickup/waiting
3. trip started
4. completed

Compare:

- Orders seen
- Sanitized samples
- Local recent rows
- Watch match badges
- Real future candidate state
- Preflight JSON only

Do not submit live to EDXEIX.
