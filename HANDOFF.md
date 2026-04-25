# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-04-25

## Project identity

- Domain: `https://gov.cabnet.app`
- GitHub repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- No frameworks, Composer, Node build tools, or heavy dependencies are approved for this project.

Expected cPanel/server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Source-of-truth order

1. Latest uploaded files, pasted code, screenshots, SQL output, SSH output, JSON output, or live audit output in the current chat.
2. `HANDOFF.md` and `CONTINUE_PROMPT.md`.
3. `README.md`, `SCOPE.md`, `DEPLOYMENT.md`, `SECURITY.md`, `docs/`, and `PROJECT_FILE_MANIFEST.md`.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

## Safety posture

The project remains at a **pre-live blocked baseline**.

- Do not enable live EDXEIX submission unless Andreas explicitly requests a live-submit update.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Never request, print, store in docs, or commit real API keys, DB passwords, tokens, cookies, CSRF values, sessions, or credentials.
- Patch zips must not include a wrapper folder. Zip root must mirror repository/live structure directly.

## Confirmed working state before this patch

- Ops console exists and is guarded: `https://gov.cabnet.app/ops/`
- Readiness/future/mappings/session/live-gate pages exist.
- Ops guard is active through:
  - `/home/cabnet/public_html/gov.cabnet.app/.user.ini`
  - `auto_prepend_file=/home/cabnet/gov.cabnet.app_app/lib/ops_guard.php`
- IP allowlist is active in `/home/cabnet/gov.cabnet.app_config/ops.php`.
- EDXEIX session capture via Firefox extension works.
- EDXEIX cookie/CSRF session file is saved server-side and never printed.
- Manual Cookie/CSRF form was removed from `/ops/edxeix-session.php`.
- `/ops/edxeix-session.php` is diagnostic/readiness focused.
- Clear Saved EDXEIX Session button exists.
- EDXEIX submit URL is configured as `https://edxeix.yme.gov.gr/dashboard/lease-agreement`.
- Firefox extension in `tools/firefox-edxeix-session-capture/` reached at least version `0.1.2`.
- EDXEIX session diagnostic showed `cookie_present: true`, `csrf_present: true`, `placeholder_detected: false`, `ready: true`, and `edxeix_submit_url_configured: true`.
- Live submit gate exists at `https://gov.cabnet.app/ops/live-submit.php`.
- Live HTTP transport remains intentionally blocked.
- `live_submit_enabled` is false.
- `http_submit_enabled` is false.
- No live EDXEIX submission has been performed.

## Known mappings

Known driver reference IDs from EDXEIX dropdown:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

Current operating note:

- Leave Georgios Zachariou unmapped unless his exact EDXEIX driver ID is independently confirmed.
- Use Filippos for the first live-safe test.

Known mapped driver/vehicle used in testing:

```text
Filippos Giannakopoulos
Bolt UUID: 57256761-d21b-4940-a3ca-bdcec5ef6af1
EDXEIX driver ID: 17585

EMX6874
Mercedes-Benz Vito Tourer 2019
EDXEIX vehicle ID: 13799

EHA2545
EDXEIX vehicle ID: 5949
```

## Important live Bolt test result from 2026-04-25

A real Bolt rider-app test was performed with Andreas as rider and Filippos as driver.

Facts:

- Driver: Filippos Giannakopoulos
- Vehicle: EMX6874
- Ride was immediate/near pickup, not 30+ minutes in future.
- Bolt Rider app showed around 9 minutes to pickup.
- Filippos accepted the ride.
- Filippos marked passenger picked up/waiting.
- Filippos started the trip.
- The app repeatedly ran:
  - `https://gov.cabnet.app/bolt_sync_orders.php`
  - `https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30`

Visibility timeline confirmed:

```text
Ride accepted / assigned: orders_seen: 0
Passenger picked up / waiting: orders_seen: 0
Trip started: orders_seen: 0
Trip completed: orders_seen: 1
```

After completion:

```text
orders_seen: 1
action: inserted
id: 11
external_order_id: MjI3Mi0yNDgwMjc1MjQ5LTMyMzIxMTY1OTI
edxeix_ready: 1
```

Conclusion:

The current Bolt Fleet orders endpoint did not expose the active trip before completion during this test. This may block true pre-departure EDXEIX readiness unless Bolt provides a different endpoint, webhook, scheduled order feed, or a test with a scheduled future ride behaves differently.

## Patch added after this baseline

Patch name:

```text
Bolt API Visibility Diagnostic
```

Files added:

```text
gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php
public_html/gov.cabnet.app/ops/bolt-api-visibility.php
public_html/gov.cabnet.app/ops/bolt-api-visibility-run.php
docs/BOLT_API_VISIBILITY_DIAGNOSTIC.md
PATCH_README.md
```

Purpose:

- Add a guarded ops page for repeatable Bolt visibility checks.
- Probe the existing Bolt order sync path in dry-run mode only.
- Optionally record sanitized private timeline rows to:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/bolt-api-visibility/YYYY-MM-DD.jsonl
```

New URLs:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php
https://gov.cabnet.app/ops/bolt-api-visibility-run.php
```

Safety:

- Does not enable live EDXEIX submission.
- Does not call EDXEIX.
- Does not stage EDXEIX jobs.
- Does not print raw Bolt payloads.
- Does not store secrets, cookies, CSRF values, passenger names, phone numbers, or emails.

## Recommended next step

Deploy the Bolt API Visibility Diagnostic patch, then run one safe snapshot:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=0&hours_back=24&sample_limit=20
```

During the next real Filippos/EMX6874 test, run recorded snapshots at:

1. Accepted/assigned
2. Pickup/waiting
3. Trip started
4. Trip completed

Suggested recorded watch URL:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=1&hours_back=24&sample_limit=20&watch_driver_uuid=57256761-d21b-4940-a3ca-bdcec5ef6af1&watch_vehicle_plate=EMX6874&label=filippos-emx6874-probe
```

Suggested auto-refresh URL:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=1&hours_back=24&sample_limit=20&watch_driver_uuid=57256761-d21b-4940-a3ca-bdcec5ef6af1&watch_vehicle_plate=EMX6874&label=filippos-emx6874-probe&refresh=20
```

## Git commit

Title:

```text
Add Bolt API visibility diagnostic
```

Description:

```text
Adds a guarded, read-only Bolt API visibility diagnostic for the gov.cabnet.app Bolt → EDXEIX bridge. The new ops page and JSON endpoint probe the existing Bolt order sync path in dry-run mode only, summarize sanitized visibility snapshots, optionally record a private JSONL timeline, and provide a repeatable workflow for confirming whether active Bolt trips are visible before completion. No EDXEIX live submission, EDXEIX HTTP transport, queue staging, SQL changes, secrets, raw payloads, cookies, or session data are introduced.
```
