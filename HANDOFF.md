# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved. Do not add automatic submission behavior. Work must remain read-only, dry-run, preflight, queue, local-only, or explicit manual verification unless the owner explicitly asks for a live submission patch after a real eligible future Bolt trip exists.

## Current known state after dry-run harness validation

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Required DB tables exist in the working server line.
- Mapping coverage remains partial: latest visible readiness showed 1/2 drivers and 2/15 vehicles mapped.
- Historical Bolt rows are correctly blocked because they are terminal/cancelled and not in the future.
- A LAB/local future booking test row was created successfully:
  - normalized booking ID: `10`
  - order reference: `LAB-LOCAL-FUTURE-20260425105607-8943`
  - source system: `lab_local_test`
- A local dry-run submission job was created successfully:
  - submission job ID: `2`
  - status: `staged_dry_run`
- A local dry-run worker attempt was recorded successfully:
  - attempt ID: `1`
  - no EDXEIX submission was performed
- Readiness showed zero live attempts indicated.
- Readiness may remain `NOT_READY` while LAB rows/jobs/attempts exist and while mappings are incomplete. That is expected and safe.

## Latest safety-output patch

The latest patch clarifies the JSON language for LAB/test rows:

- `technical_payload_valid` means mapping/time/status checks pass.
- `dry_run_allowed` or `dry_run_stage_allowed` means local dry-run processing is allowed.
- `live_submission_allowed` means a row is eligible for live EDXEIX consideration.
- `submission_safe` now follows `live_submission_allowed`.
- LAB/test/never-live rows should show `live_submission_allowed: false` even when their technical payload is valid.

## Key files

```text
gov.cabnet.app_app/lib/bolt_sync_lib.php
gov.cabnet.app_app/src/TestBookingFactory.php
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
public_html/gov.cabnet.app/ops/test-booking.php
```

## Recent SQL files

```text
gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
gov.cabnet.app_sql/2026_04_25_mark_local_dry_run_attempts.sql
```

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/cleanup scripts unless explicitly documented and safe

## Recommended next work

1. Upload and verify the LAB/test safety-output patch.
2. Run the optional dry-run attempt marker SQL if the existing attempt row remains unclassified in readiness.
3. Keep readiness audit clean.
4. Add authentication/IP restriction around `/ops` and public JSON endpoints before broader exposure.
5. Continue mapping remaining drivers/vehicles.
6. Schedule a real Bolt ride 40–60 minutes in the future for the first true real-world preflight candidate.
7. Only after successful real preflight, design a separately gated live-submit patch.
