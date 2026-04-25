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
6. Operations pages exist: /ops/bolt-live.php, /ops/jobs.php, /ops/readiness.php, /ops/test-booking.php.
7. JSON/report endpoints exist: /bolt_sync_reference.php, /bolt_sync_orders.php, /bolt_edxeix_preflight.php, /bolt_jobs_queue.php, /bolt_stage_edxeix_jobs.php, /bolt_readiness_audit.php, /bolt_submission_worker.php.
8. Real existing Bolt rows are blocked correctly because they are terminal/cancelled and not at least +30 minutes in the future.
9. A LAB/local dry-run future booking was created and validated through preflight, local staging, and worker dry-run attempt recording.
10. No EDXEIX live submission was performed. Live submission remains intentionally unimplemented.

Latest completed/active patch direction:
- Make LAB/test safety explicit in preflight/stage/worker output.
- Separate technical payload validity from live submission eligibility.
- Expected fields:
  - technical_payload_valid
  - dry_run_allowed or dry_run_stage_allowed
  - live_submission_allowed
  - technical_blockers
  - dry_run_blockers or stage_blockers
  - live_blockers
- `submission_safe` should mean live submission allowed, not merely payload-valid.

Next live test blocker:
A real Bolt ride must be scheduled at least 40–60 minutes in the future before a true live-safe EDXEIX candidate can exist.

When continuing:
1. Ask for latest project files or specific file contents if needed.
2. Inspect uploaded files before patching.
3. Prefer the smallest safe patch.
4. Include exact cPanel upload paths.
5. Include suggested Git commit title and description.
6. Keep live submission disabled unless explicitly requested.
