# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved. Do not add automatic submission behavior. Work must remain read-only, dry-run, preflight, queue, local-only, or guarded admin-only unless Andreas explicitly asks for live submission after a real eligible future Bolt trip exists.

## Current known state

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Readiness audit and UI are operational.
- Ops access guard is installed and verified.
- Dry-run future booking harness was validated end-to-end.
- LAB cleanup tool was validated and cleanup returned the system to a clean state.
- Mapping dashboard is operational and JSON output is sanitized.
- Mapping editor is guarded and audit-logged.
- Known EDXEIX driver references are displayed in `/ops/mappings.php`:
  - `1658` — `ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ`
  - `17585` — `ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ`
  - `6026` — `ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ`
- Georgios Zachariou remains intentionally unmapped until his exact EDXEIX driver ID is independently confirmed.
- New page: `/ops/future-test.php` shows a read-only checklist for the next real Bolt future-ride preflight.
- Live EDXEIX submission is still disabled and not authorized.

## Key files

```text
gov.cabnet.app_app/lib/bolt_sync_lib.php
gov.cabnet.app_app/lib/ops_guard.php
public_html/gov.cabnet.app/.user.ini
public_html/gov.cabnet.app/bolt_sync_reference.php
public_html/gov.cabnet.app/bolt_sync_orders.php
public_html/gov.cabnet.app/bolt_edxeix_preflight.php
public_html/gov.cabnet.app/bolt_stage_edxeix_jobs.php
public_html/gov.cabnet.app/bolt_jobs_queue.php
public_html/gov.cabnet.app/bolt_readiness_audit.php
public_html/gov.cabnet.app/bolt_submission_worker.php
public_html/gov.cabnet.app/ops/bolt-live.php
public_html/gov.cabnet.app/ops/jobs.php
public_html/gov.cabnet.app/ops/readiness.php
public_html/gov.cabnet.app/ops/mappings.php
public_html/gov.cabnet.app/ops/test-booking.php
public_html/gov.cabnet.app/ops/cleanup-lab.php
public_html/gov.cabnet.app/ops/future-test.php
```

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- Real `gov.cabnet.app_config/database.php`
- Real `gov.cabnet.app_config/app.php`
- Real `gov.cabnet.app_config/edxeix.php`
- Real `gov.cabnet.app_config/ops.php`
- EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/cleanup scripts

## Recommended next work

1. Verify `/ops/future-test.php` and `/ops/future-test.php?format=json`.
2. Keep Georgios Zachariou unmapped until his EDXEIX driver ID is confirmed.
3. Confirm EDXEIX vehicle IDs only for vehicles intended for real testing.
4. When possible, create a real Bolt ride 40–60 minutes in the future using Filippos and a mapped vehicle.
5. Use preflight-only validation first. Do not enable live EDXEIX submission without explicit approval.
