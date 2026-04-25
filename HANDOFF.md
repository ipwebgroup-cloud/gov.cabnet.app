# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current baseline

The project is in safe production-prep state.

- Safe ops dashboard: installed.
- Readiness/future-test workflow: installed.
- Mapping dashboard/editor: installed.
- Live submit gate: installed but live HTTP transport is intentionally blocked.
- EDXEIX submit URL: configured server-side as `https://edxeix.yme.gov.gr/dashboard/lease-agreement`.
- EDXEIX cookie/CSRF session: saved server-side and placeholder-free.
- Firefox extension: installed/working locally for operator session capture.
- `/ops/edxeix-session-capture.php`: guarded extension endpoint installed.
- `/ops/edxeix-session.php`: diagnostic/read-only operator page; manual Cookie/CSRF input fields removed to avoid confusion.
- Real future Bolt candidates: 0.
- Live-eligible rows: 0.
- Live HTTP execution: no.

## Current remaining blockers before real live EDXEIX submission

1. Create/sync one real future Bolt ride with Filippos and a mapped vehicle.
2. Confirm `/ops/future-test.php` detects a real future candidate.
3. Confirm `/bolt_edxeix_preflight.php?limit=30` shows a valid payload.
4. Apply the final explicitly approved live HTTP transport patch.
5. Enable live flags only for the approved one-shot test, then disable again after audit.

## Known safe first-test mappings

Driver:

- Filippos Giannakopoulos → EDXEIX driver ID `17585`

Vehicles:

- EMX6874 → EDXEIX vehicle ID `13799`
- EHA2545 → EDXEIX vehicle ID `5949`

Leave Georgios Zachariou unmapped for now.

## Firefox extension workflow

1. Log in to EDXEIX.
2. Open `https://edxeix.yme.gov.gr/dashboard/lease-agreement/create`.
3. Click the CABnet EDXEIX Capture Firefox extension.
4. Click **Capture from EDXEIX tab**.
5. Click **Save to gov.cabnet.app**.
6. Verify `/ops/edxeix-session.php` and `/ops/live-submit.php`.

## Safety rules

- Do not enable live submission without explicit approval.
- Do not submit historical, finished, cancelled, expired, terminal, LAB, or test rows.
- Do not commit real config, cookies, CSRF tokens, logs, sessions, or runtime files.
- Keep `/home/cabnet/gov.cabnet.app_config/live_submit.php` server-only.
- Keep `/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json` server-only.
