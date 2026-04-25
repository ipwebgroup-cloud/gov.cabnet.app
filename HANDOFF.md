# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved. Do not add automatic submission behavior. Work must remain read-only, dry-run, preflight, queue, local-only, or guarded admin-only unless the owner explicitly asks for live submission after a real eligible future Bolt trip exists.

## Current known state

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Required DB tables exist in the working server line.
- Historical Bolt orders are correctly blocked because they are terminal/cancelled and not +30 minutes in the future.
- Dry-run future booking harness exists at `/ops/test-booking.php` and was validated end-to-end.
- LAB/test safety output separates technical payload validity from live submission eligibility.
- LAB dry-run cleanup tool exists at `/ops/cleanup-lab.php` and cleanup returned the system to a clean state.
- Ops access guard is active via `/home/cabnet/public_html/gov.cabnet.app/.user.ini` and `/home/cabnet/gov.cabnet.app_app/lib/ops_guard.php`.
- Real guard config exists server-side only at `/home/cabnet/gov.cabnet.app_config/ops.php` and must not be committed.
- Latest verified readiness showed `READY_FOR_REAL_BOLT_FUTURE_TEST`, with no LAB rows, no local jobs, and no submission attempts.
- Mapping coverage remains partial: latest known readiness showed 1/2 drivers and 2/15 vehicles mapped.
- Mapping coverage dashboard added at `/ops/mappings.php`.

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
public_html/gov.cabnet.app/ops/submit.php
public_html/gov.cabnet.app/ops/test-booking.php
public_html/gov.cabnet.app/ops/cleanup-lab.php
```

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- Real `gov.cabnet.app_config/ops.php`
- Real EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/cleanup scripts

## Recommended next work

1. Verify `/ops/mappings.php` from Andreas' allowed IP.
2. Use the mapping dashboard to identify unmapped drivers/vehicles.
3. Add a guarded mapping edit/update page only after the read-only dashboard is confirmed.
4. Schedule a real Bolt ride 40–60 minutes in the future for the first true preflight candidate.
5. Only after successful real preflight, design a separately gated live-submit patch.
