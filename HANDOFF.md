# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved. Do not add automatic submission behavior. Work must remain read-only, dry-run, preflight, queue, cleanup, access-guarded, or local-only unless the owner explicitly asks for live submission after a real eligible future Bolt trip exists.

## Current known state

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Required DB tables exist in the working server line.
- Dry-run future booking harness was validated end-to-end.
- LAB cleanup tool cleaned local LAB booking/job/attempt rows successfully.
- Ops access guard is installed via `.user.ini` and is active.
- Readiness reached `READY_FOR_REAL_BOLT_FUTURE_TEST` after cleanup.
- Mapping coverage dashboard exists at `/ops/mappings.php`.
- Mapping coverage latest observed: `1/2` drivers mapped and `2/15` vehicles mapped.
- Mapping JSON output has been sanitized so raw payloads are not returned.

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
public_html/gov.cabnet.app/ops/test-booking.php
public_html/gov.cabnet.app/ops/cleanup-lab.php
public_html/gov.cabnet.app/ops/mappings.php
```

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- Real `gov.cabnet.app_config/edxeix.php`
- Real `gov.cabnet.app_config/ops.php`
- EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/cleanup scripts

## Recommended next work

1. Verify `/ops/mappings.php?format=json` no longer returns `raw_payload_json`.
2. Add a guarded mapping update workflow for missing EDXEIX driver/vehicle IDs.
3. Keep live submission disabled until a real future Bolt trip exists and preflight passes.
