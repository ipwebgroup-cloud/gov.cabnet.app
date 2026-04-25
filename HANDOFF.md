# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved. Do not add automatic submission behavior. Work must remain read-only, dry-run, preflight, queue visibility, or local-only unless Andreas explicitly asks for a live-submit patch after a real eligible future Bolt trip exists and preflight passes.

## Current known state

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Ops access guard is active through `.user.ini` and `/home/cabnet/gov.cabnet.app_config/ops.php`.
- Readiness currently reached `READY_FOR_REAL_BOLT_FUTURE_TEST` after LAB cleanup.
- Dry-run future booking harness was validated end-to-end.
- LAB cleanup tool was validated and removed local test rows/jobs/attempts.
- Mapping dashboard/editor exists at `/ops/mappings.php`.
- Mapping JSON output is sanitized and excludes `raw_payload_json`.
- Known EDXEIX driver references are displayed as reference-only notes.
- Real future-test checklist exists at `/ops/future-test.php`.
- Legacy `/ops/index.php` has now been replaced by a safe read-only operations landing page.
- Live EDXEIX submission remains disabled and intentionally unimplemented.

## Key current ops routes

```text
/ops/index.php
/ops/readiness.php
/ops/future-test.php
/ops/mappings.php
/ops/jobs.php
/ops/bolt-live.php
/ops/test-booking.php
/ops/cleanup-lab.php
/bolt_readiness_audit.php
/bolt_edxeix_preflight.php?limit=30
/bolt_jobs_queue.php?limit=50
```

## Mapping notes

Known EDXEIX driver IDs from the current dropdown:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

Current operational decision: leave Georgios Zachariou unmapped unless his exact EDXEIX driver ID is independently confirmed.

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- Real `gov.cabnet.app_config/ops.php`
- EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/cleanup scripts

## Recommended next work

1. Verify `/ops/index.php` now shows the safe landing page only.
2. Wait for a real Bolt ride scheduled at least 40–60 minutes in the future using a mapped driver and mapped vehicle.
3. Run `/bolt_sync_orders.php`, then `/ops/future-test.php`, then `/bolt_edxeix_preflight.php?limit=30`.
4. Do not enable live EDXEIX submission until explicitly requested.
