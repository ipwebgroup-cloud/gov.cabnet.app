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
- Mapping coverage is partial; at least one mapped driver and vehicle are needed for test harness use.
- Historical Bolt orders were mapping-ready but not submission-safe because they were terminal/cancelled and not +30 minutes in the future.
- Readiness audit exists at `/bolt_readiness_audit.php`.
- Readiness UI exists at `/ops/readiness.php`.
- Local dry-run future booking harness added in this patch at `/ops/test-booking.php`.

## Latest patch: dry-run future booking simulation harness

Added:

```text
gov.cabnet.app_app/src/TestBookingFactory.php
public_html/gov.cabnet.app/ops/test-booking.php
gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
docs/DRY_RUN_TEST_BOOKING_HARNESS.md
```

Purpose:

- Create a synthetic future `normalized_bookings` row when no real future Bolt ride is available.
- Use only existing mapped driver and vehicle records.
- Mark synthetic rows as `lab_local_test` and `LAB-LOCAL-FUTURE-*`.
- Keep the workflow dry-run/local only.
- Preserve the rule that LAB rows require `allow_lab=1` for local queue/worker testing.

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

## Test harness verification URLs

```text
https://gov.cabnet.app/ops/test-booking.php
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30
https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30&allow_lab=1
https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30&create=1&allow_lab=1
https://gov.cabnet.app/bolt_submission_worker.php?limit=30&allow_lab=1
https://gov.cabnet.app/bolt_submission_worker.php?limit=30&record=1&allow_lab=1
https://gov.cabnet.app/ops/readiness.php
```

## Do not commit

- Real `gov.cabnet.app_config/config.php`
- Real `gov.cabnet.app_config/bolt.php`
- EDXEIX session files/cookies/CSRF tokens
- Raw SQL data dumps
- Logs/artifacts/runtime files
- Temporary public fix/cleanup scripts

## Recommended next work

1. Upload the dry-run future booking harness patch.
2. Run the additive SQL migration.
3. Use `/ops/test-booking.php` to create one local LAB future booking.
4. Verify normal staging blocks LAB rows without `allow_lab=1`.
5. Verify local dry-run staging and worker audit with `allow_lab=1`.
6. Keep live submission disabled.
7. Later, schedule a real Bolt ride 40–60 minutes in the future for the first true real-trip preflight candidate.
8. Only after successful real preflight, design a separately gated live-submit patch.
