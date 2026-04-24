# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved. Do not add automatic submission behavior. Work must remain read-only, dry-run, preflight, queue, or local-only unless the owner explicitly asks for live submission after a real eligible future Bolt trip exists.

## Current known state

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Required DB tables exist in the working server line.
- Mapping coverage is partial: at latest validation, 1/2 drivers and 2/15 vehicles were mapped.
- Historical Bolt orders were mapping-ready but not submission-safe because they were terminal/cancelled and not +30 minutes in the future.
- Lab candidate rows/jobs were cleaned up.
- Readiness audit exists at `/bolt_readiness_audit.php`.
- Readiness UI exists at `/ops/readiness.php`.

## Key files

```text
gov.cabnet.app_app/lib/bolt_sync_lib.php
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
public_html/gov.cabnet.app/ops/submit.php
```

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/cleanup scripts

## Recommended next work

1. Delete any temporary public utilities from production after verification.
2. Keep readiness audit clean.
3. Add authentication/IP restriction around `/ops` and public JSON endpoints before broader exposure.
4. Schedule a real Bolt ride 40–60 minutes in the future for the first true preflight candidate.
5. Only after successful preflight, design a separately gated live-submit patch.
