You are continuing development of the gov.cabnet.app Bolt → EDXEIX integration project.

Project context:
- Domain: https://gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Public webroot: /home/cabnet/public_html/gov.cabnet.app
- Private folders: /home/cabnet/gov.cabnet.app_app, /home/cabnet/gov.cabnet.app_config, /home/cabnet/gov.cabnet.app_sql
- Treat latest uploaded files as source of truth.
- Do not ask for API keys, DB passwords, cookies, CSRF tokens, or other secrets. Use placeholders and assume secrets already exist in server config files.

Current integration goal:
Build and harden a safe Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue/readiness workflow.

Important safety rule:
No live EDXEIX submission has been performed or approved. Do not create automatic live submission behavior. Keep all work read-only, dry-run, preflight, queue, or local-only unless explicitly asked for a live submit patch after a real eligible future Bolt trip exists.

Validated status:
1. Bolt API connection works.
2. Bolt reference sync works.
3. Bolt order sync works.
4. Schema compatibility issues for dedupe/defaults/price/null ended_at were resolved.
5. Some Bolt driver/vehicle mappings are present, but not all imported vehicles are mapped.
6. Operations pages exist: /ops/bolt-live.php, /ops/jobs.php, /ops/readiness.php.
7. JSON/report endpoints exist: /bolt_sync_reference.php, /bolt_sync_orders.php, /bolt_edxeix_preflight.php, /bolt_jobs_queue.php, /bolt_stage_edxeix_jobs.php, /bolt_readiness_audit.php, /bolt_submission_worker.php.
8. Queue was clean at latest validation: zero local submission jobs and zero recent attempts.
9. Real existing Bolt rows are blocked correctly because they are terminal/cancelled and not at least +30 minutes in the future.
10. A dry-run future booking simulation harness has now been prepared.

Latest patch:
- gov.cabnet.app_app/src/TestBookingFactory.php
- public_html/gov.cabnet.app/ops/test-booking.php
- gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
- docs/DRY_RUN_TEST_BOOKING_HARNESS.md

Harness purpose:
- Create a synthetic `lab_local_test` future booking using one existing mapped driver and one existing mapped vehicle.
- Use order references beginning with `LAB-LOCAL-FUTURE`.
- Mark the synthetic normalized booking row with `is_test_booking=1`, `never_submit_live=1`, and `live_submit_block_reason` after the SQL migration. The migration also prepares matching job columns for a later propagation patch.
- Allow testing of preflight, local queue staging, worker dry-run audit, and readiness without a real future Bolt ride.
- Never submit harness rows to EDXEIX live.

Verification sequence:
1. Upload patch files to the exact cPanel paths.
2. Run `gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql` once.
3. Open https://gov.cabnet.app/ops/test-booking.php
4. Create one local dry-run booking by typing `CREATE LOCAL DRY RUN BOOKING`.
5. Open https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
6. Open https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30 and confirm the LAB row is blocked.
7. Open https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30&allow_lab=1 to preview local LAB staging.
8. Open https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30&create=1&allow_lab=1 to create a local staged job only.
9. Open https://gov.cabnet.app/bolt_submission_worker.php?limit=30&allow_lab=1 for worker preview.
10. Open https://gov.cabnet.app/bolt_submission_worker.php?limit=30&record=1&allow_lab=1 to record a local dry-run attempt only.
11. Open https://gov.cabnet.app/ops/readiness.php.

Next live test blocker:
A real Bolt ride must be scheduled at least 40–60 minutes in the future before a true live-safe EDXEIX candidate can exist.

When continuing:
1. Ask for latest project files or specific file contents if needed.
2. Inspect uploaded files before patching.
3. Prefer the smallest safe patch.
4. Include exact cPanel upload paths.
5. Include suggested Git commit title and description.
6. Keep live submission disabled unless explicitly requested.
