# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved. Do not add automatic submission behavior. Work must remain read-only, dry-run, preflight, queue, local-only, or cleanup-only unless Andreas explicitly asks for live submission after a real eligible future Bolt trip exists.

## Current known state

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Required DB tables exist in the working server line.
- Mapping coverage is partial: latest visible readiness showed 1/2 drivers and 2/15 vehicles mapped.
- Historical Bolt orders are blocked correctly because they are terminal/cancelled and/or not sufficiently in the future.
- A LAB/local future booking harness exists at `/ops/test-booking.php`.
- LAB/test rows are marked with `is_test_booking`, `never_submit_live`, and `test_booking_created_by` when the schema columns exist.
- LAB/test preflight/worker output separates technical dry-run validity from live submission eligibility.
- A LAB dry-run cleanup utility now exists at `/ops/cleanup-lab.php`.
- Readiness audit exists at `/bolt_readiness_audit.php`.
- Readiness UI exists at `/ops/readiness.php`.
- Jobs UI exists at `/ops/jobs.php`.

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
public_html/gov.cabnet.app/ops/test-booking.php
public_html/gov.cabnet.app/ops/cleanup-lab.php
public_html/gov.cabnet.app/ops/submit.php
```

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/diagnostic scripts unless explicitly part of a safe patch

## Recommended next work

1. Upload and verify `/ops/cleanup-lab.php`.
2. Use the cleanup page to remove the current LAB/local test row, local job, and dry-run attempt after validation.
3. Confirm readiness returns to a clean dry-run state with zero LAB rows/jobs/attempts and zero live attempts.
4. Improve authentication/IP restriction around `/ops` and public JSON endpoints before broader exposure.
5. Continue mapping remaining Bolt drivers/vehicles.
6. Schedule a real Bolt ride 40–60 minutes in the future for the first true preflight candidate.
7. Only after successful real future preflight, design a separately gated live-submit patch.
