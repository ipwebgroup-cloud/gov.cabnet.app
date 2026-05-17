# gov.cabnet.app — Live Site Route & DB De-bloat Audit

Generated: 2026-05-15  
Input package: `gov_cabnet_git_safe_with_db_audit_20260515_082730.zip`  
Purpose: private Sophion live-site audit only. This package is not for GitHub commit.

## Executive summary

The live site is functional and the latest package hygiene fix is working. The uploaded DB-audit package includes `DATABASE_EXPORT.sql` for private audit, but it no longer includes runtime EDXEIX session storage, proof artifacts, `.bak`/`.pre_` residue, or real config files.

Current state:

| Area | Result |
|---|---:|
| Package entries/files scanned | 741 filesystem entries |
| PHP files in package | 296 |
| Public-root PHP routes | 12 |
| `/ops` PHP routes | 159 |
| Database tables | 30 |
| V3 queue rows | 11 |
| Submission jobs | 0 |
| Submission attempts | 0 |

Main finding: the app is safe enough to operate in closed-gate mode, but the live interface and route surface are too broad. The next de-bloat should be no-delete/no-SQL: reorganize navigation, update route inventory, and move dev/diagnostic routes out of the daily operator path.

## Confirmed safety posture

- Global auth prepend exists: `public_html/gov.cabnet.app/.user.ini` loads `_auth_prepend.php`.
- Live curl checks showed protected routes redirect to `/ops/login.php`.
- V3 live submission remains disabled.
- V3 `edxeix_live` adapter remains skeleton-only/non-live.
- `submission_jobs` and `submission_attempts` are empty in the DB export.
- V3 queue rows are blocked/expired in the DB snapshot, with no V3 submitted rows.
- DB audit package contains `DATABASE_EXPORT.sql` intentionally and must remain private.

## Package hygiene

PASS for private DB audit use:

- `DATABASE_EXPORT.sql`: present by design.
- `GIT_SAFE_WITH_DB_AUDIT_NOTICE.md`: present.
- `gov.cabnet.app_config_examples/`: present.
- real `gov.cabnet.app_config/`: not present.
- `storage/runtime/`: not present.
- `storage/artifacts/`: not present.
- `edxeix_session.json`: not present.
- `.bak` / `.pre_` package residue: not present.

## Route surface summary

| Classification | Count |
|---|---:|
| HIDE/ARCHIVE | 28 |
| KEEP | 98 |
| KEEP ADMIN/AUDIT | 17 |
| KEEP INTERNAL | 5 |
| KEEP SEPARATE | 2 |
| LOCKED REVIEW | 21 |


| Area | Count |
|---|---:|
| ops | 159 |
| public_root | 12 |


### Key observation

The live app currently has more operational/dev/audit routes than the visible route index documents. `/ops/route-index.php` currently lists only a small curated safety matrix, while the actual live package has 171 PHP public/ops routes. This is not automatically unsafe because auth prepend is active, but it creates operator confusion and increases maintenance burden.

## Public-root routes

These routes live outside `/ops`. They are still protected by `.user.ini` auth prepend, but they should be reviewed because public-root utility endpoints are easier to forget during future cleanup.

| Route | Recommendation | Tags | Static risk flags | Reason |
|---|---|---|---|---|
{table_routes(public_routes)}

Recommended direction: do not delete now. Later, either keep only `index.php` plus `_auth_prepend.php` at public root, or redirect the older Bolt utility endpoints into `/ops` equivalents after confirming no cron/manual workflows depend on them.

## Locked-review routes

These routes are submit-related or adjacent to submit flow. They should remain protected and should not be placed in the daily operator navigation unless the page is clearly read-only/blocked.

| Route | Recommendation | Tags | Static risk flags | Reason |
|---|---|---|---|---|
{table_routes(locked)}

## Hide/archive candidates

These are not necessarily dangerous; they are development, diagnostic, mobile-dev, probe, test, or simulation routes. They should be hidden from primary navigation and grouped under a Developer Archive / Evidence Archive page.

| Route | Recommendation | Tags | Static risk flags | Reason |
|---|---|---|---|---|
{table_routes(hide)}

## Current V3 audit routes to keep visible

These are in scope for closed-gate V3 proof/readiness and should remain available to admin operators.

| Route | Recommendation | Tags | Static risk flags | Reason |
|---|---|---|---|---|
{table_routes(keepaudit)}

## Database table audit

| Table | Rows | PHP reference count | Category |
|---|---:|---:|---|
| `backup_normalized_bookings_v6_2_2_bad_20260508_120503` | 22 | 0 | cleanup candidate |
| `bolt_mail_driver_notifications` | 74 | 34 | Bolt/intake/bookings |
| `bolt_mail_dry_run_evidence` | 9 | 19 | Bolt/intake/bookings |
| `bolt_mail_intake` | 87 | 66 | Bolt/intake/bookings |
| `bolt_raw_payloads` | 123 | 10 | Bolt/intake/bookings |
| `edxeix_export_drivers` | 33 | 3 | mapping/governance |
| `edxeix_export_lessors` | 8 | 26 | mapping/governance |
| `edxeix_export_starting_points` | 15 | 19 | mapping/governance |
| `edxeix_export_vehicles` | 23 | 3 | mapping/governance |
| `edxeix_live_submission_audit` | 5 | 11 | submit audit/queue |
| `mapping_drivers` | 33 | 132 | mapping/governance |
| `mapping_lessor_starting_points` | 3 | 74 | mapping/governance |
| `mapping_starting_points` | 2 | 23 | mapping/governance |
| `mapping_update_audit` | 1 | 3 | mapping/governance |
| `mapping_vehicles` | 34 | 60 | mapping/governance |
| `mapping_verification_status` | 0 | 12 | mapping/governance |
| `mobile_submit_evidence_log` | 1 | 32 | mobile evidence/dev |
| `normalized_bookings` | 113 | 133 | Bolt/intake/bookings |
| `ops_audit_log` | 30 | 37 | ops/auth/admin |
| `ops_edxeix_submit_captures` | 1 | 42 | ops/auth/admin |
| `ops_login_attempts` | 30 | 31 | ops/auth/admin |
| `ops_user_preferences` | 0 | 11 | ops/auth/admin |
| `ops_users` | 1 | 46 | ops/auth/admin |
| `pre_ride_email_v3_live_submit_approvals` | 3 | 57 | V3 current scope |
| `pre_ride_email_v3_queue` | 11 | 226 | V3 current scope |
| `pre_ride_email_v3_queue_events` | 189 | 64 | V3 current scope |
| `pre_ride_email_v3_starting_point_options` | 3 | 63 | V3 current scope |
| `receipt_issuance_attempts` | 65 | 20 | AADE receipt separate |
| `submission_attempts` | 0 | 200 | submit audit/queue |
| `submission_jobs` | 0 | 229 | submit audit/queue |

### DB findings

- `pre_ride_email_v3_*` tables are current-scope V3 and should be kept.
- `submission_jobs` and `submission_attempts` are empty, which is good for current closed-gate posture.
- `edxeix_live_submission_audit` contains historical guarded/manual V6-era evidence; it is not evidence of V3 automatic live submission.
- `backup_normalized_bookings_v6_2_2_bad_20260508_120503` has no PHP references and should be marked cleanup candidate only. Do not drop without explicit backup/approval.

## Recommended de-bloat phases

### Phase 1 — no-delete UI/navigation cleanup

Goal: reduce daily operator clutter without deleting code or changing DB.

- Primary workflow should show only: Ops Home, V3 Control Center/Monitor, Production Pre-Ride Tool, Live Operator Console, Mapping Review, Handoff Center.
- Move V2/dev/test/mobile/research/simulation/probe pages into a clearly labeled `Developer Archive` or `Evidence Archive` section.
- Keep route access possible for admins, but remove developer pages from the main sidebar.
- Update `/ops/route-index.php` or create `/ops/live-route-inventory.php` to reflect the actual live route count.

### Phase 2 — public-root endpoint review

Goal: reduce forgotten utility endpoints at webroot.

- Confirm whether any cron/browser workflow still uses `bolt_sync_orders.php`, `bolt_sync_reference.php`, `bolt_stage_edxeix_jobs.php`, or `bolt_submission_worker.php`.
- If not actively used, keep them auth-protected but mark deprecated.
- Later replace with `/ops` or CLI-only equivalents.

### Phase 3 — DB cleanup plan

Goal: cleanup without data loss.

- Prepare a backup-verification SQL note for `backup_normalized_bookings_v6_2_2_bad_20260508_120503`.
- Do not run destructive SQL until Andreas explicitly approves.

### Phase 4 — live-submit architecture review

Goal: keep V3 closed-gate until real submitter is intentionally implemented.

- Maintain `enabled=false`, `mode=disabled`, `adapter=disabled`, `hard_enable_live_submit=false`.
- Keep adapter skeleton non-live.
- Continue to require real future row + operator approval + verified starting point + preflight pass.

## Recommended immediate patch

Prepare a no-delete navigation de-bloat patch for `_shell.php` and route inventory docs only:

- No SQL.
- No route deletion.
- No live submit changes.
- No V0 changes.
- Reorganize visible navigation so daily operators see fewer dev/probe pages.
- Add/update an admin route inventory page to expose full route classification.

