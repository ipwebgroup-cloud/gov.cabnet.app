# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved. Do not add automatic submission behavior. Work must remain read-only, dry-run, preflight, queue, local-only, or guarded admin mapping updates unless the owner explicitly asks for live submission after a real eligible future Bolt trip exists.

## Current known state

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Dry-run future booking harness was validated end-to-end.
- LAB/test cleanup tool was validated and readiness returned to a clean state.
- Ops access guard is installed through `.user.ini` and enabled with server-only config.
- Readiness reached `READY_FOR_REAL_BOLT_FUTURE_TEST` after cleanup.
- Mapping dashboard exists at `/ops/mappings.php` and JSON output is sanitized.
- Current mapping coverage around latest validation: 1/2 drivers mapped, 2/15 vehicles mapped.
- This patch adds a guarded mapping editor for EDXEIX IDs only, with local audit logging.

## Key files

```text
gov.cabnet.app_app/lib/bolt_sync_lib.php
gov.cabnet.app_app/lib/ops_guard.php
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
```

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- Real `gov.cabnet.app_config/database.php`
- Real `gov.cabnet.app_config/edxeix.php`
- Real `gov.cabnet.app_config/ops.php`
- EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/cleanup scripts

## Recommended next work

1. Run `2026_04_25_mapping_update_audit.sql`.
2. Use `/ops/mappings.php?view=unmapped` to fill missing EDXEIX IDs.
3. Recheck `/ops/readiness.php`.
4. When mapping coverage is good enough, schedule a real Bolt ride 40–60 minutes in the future for the first true preflight candidate.
5. Only after successful preflight, design a separately gated live-submit patch.
