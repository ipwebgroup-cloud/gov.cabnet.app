You are continuing development of the gov.cabnet.app Bolt → EDXEIX integration project.

Project context:
- Domain: https://gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Public webroot: /home/cabnet/public_html/gov.cabnet.app
- Private folders: /home/cabnet/gov.cabnet.app_app, /home/cabnet/gov.cabnet.app_config, /home/cabnet/gov.cabnet.app_sql
- Treat latest uploaded files, pasted code, screenshots, and live audit output as source of truth.
- Do not ask for API keys, DB passwords, cookies, CSRF tokens, or other secrets.

Current integration goal:
Build and harden a safe Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue/readiness workflow.

Important safety rule:
No live EDXEIX submission has been performed or approved. Do not create automatic live submission behavior. Keep all work read-only, dry-run, preflight, queue, local-only, or cleanup-only unless explicitly asked for a live submit patch after a real eligible future Bolt trip exists.

Validated status:
1. Bolt API connection works.
2. Bolt reference sync works.
3. Bolt order sync works.
4. Schema compatibility issues for dedupe/defaults/price/null ended_at were resolved.
5. Some Bolt driver/vehicle mappings are present, but not all imported vehicles are mapped.
6. Operations pages exist: /ops/bolt-live.php, /ops/jobs.php, /ops/readiness.php.
7. JSON/report endpoints exist: /bolt_sync_reference.php, /bolt_sync_orders.php, /bolt_edxeix_preflight.php, /bolt_jobs_queue.php, /bolt_stage_edxeix_jobs.php, /bolt_submission_worker.php, /bolt_readiness_audit.php.
8. The LAB/local future booking harness exists at /ops/test-booking.php.
9. A LAB/local booking was successfully created and validated through preflight, local staging, and dry-run worker audit.
10. The worker recorded a local dry-run attempt with would_submit_to_edxeix=false and live_submission_allowed=false.
11. A cleanup utility now exists at /ops/cleanup-lab.php to safely remove LAB/local dry-run data after testing.

Current live test blocker:
A real Bolt ride must be scheduled at least 40–60 minutes in the future before a true live-safe EDXEIX candidate can exist.

When continuing:
1. Inspect latest uploaded files before patching.
2. Prefer the smallest safe patch.
3. Include exact cPanel upload paths.
4. Include SQL only when necessary and prefer additive/read-only SQL.
5. Include verification URLs or commands.
6. Include suggested Git commit title and description.
7. Keep live submission disabled unless explicitly requested.
