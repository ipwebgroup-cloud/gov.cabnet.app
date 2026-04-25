# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Source of truth

Use the latest uploaded/server files first, then this handoff, then README/docs/GitHub.

## Current validated state

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Normalized booking import works.
- EDXEIX preflight payload preview works.
- Local dry-run staging works.
- Dry-run worker/local audit works.
- LAB future-booking test harness was validated end-to-end.
- LAB cleanup tool was validated and test data was removed.
- Ops access guard is installed and active through `.user.ini`.
- `/ops/index.php` is a safe guided landing page.
- `/ops/help.php` provides novice operator guidance.
- `/ops/future-test.php` shows real future-test readiness and progress rail.
- `/ops/mappings.php` is guarded, sanitized, and can update only EDXEIX mapping IDs with audit logging.
- `/ops/live-submit.php` has been added as a disabled live-submit safety gate scaffold.

## Current readiness posture

- Readiness: `READY_FOR_REAL_BOLT_FUTURE_TEST`
- Future test: `READY TO CREATE REAL FUTURE TEST RIDE`
- Real future candidates: `0`
- LAB rows/jobs/attempts: expected `0`
- Live EDXEIX attempts: expected `0`
- Live EDXEIX HTTP submission: **disabled and intentionally blocked**

## Known mappings

Drivers:

- Filippos Giannakopoulos → EDXEIX driver ID `17585`

Vehicles:

- EMX6874 → EDXEIX vehicle ID `13799`
- EHA2545 → EDXEIX vehicle ID `5949`

Reference-only EDXEIX driver IDs:

- `1658` — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
- `17585` — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
- `6026` — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ

Leave Georgios Zachariou unmapped until his exact EDXEIX driver ID is independently confirmed.

## Live submit gate scaffold

Added:

- `gov.cabnet.app_app/lib/edxeix_live_submit_gate.php`
- `public_html/gov.cabnet.app/ops/live-submit.php`
- `gov.cabnet.app_config_examples/live_submit.example.php`
- `gov.cabnet.app_sql/2026_04_25_live_submission_audit.sql`

The gate analyzes candidates and checks safety requirements but still blocks live HTTP transport in this patch.

The real server config must be copied manually to:

`/home/cabnet/gov.cabnet.app_config/live_submit.php`

It is ignored by Git and must not be committed.

## What must not happen yet

Do not enable live EDXEIX submission yet. Do not add automatic live worker behavior yet. Do not submit historical, cancelled, terminal, expired, invalid, past, LAB, test, or unmapped Bolt rows.

## Remaining blocker before real live submission

A real future Bolt ride must be created with Filippos present and using a mapped vehicle:

- Driver: Filippos Giannakopoulos / EDXEIX `17585`
- Vehicle: EMX6874 / EDXEIX `13799`, or EHA2545 / EDXEIX `5949`
- Ride start: 40–60 minutes in the future

After that, run:

1. `/bolt_sync_orders.php`
2. `/ops/future-test.php`
3. `/bolt_edxeix_preflight.php?limit=30`
4. `/ops/live-submit.php`

Only after successful preflight and explicit Andreas approval should a separate live HTTP execution patch be created.
