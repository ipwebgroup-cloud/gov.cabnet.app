You are continuing development of the gov.cabnet.app Bolt → EDXEIX integration project.

Project identity:
- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Public webroot: /home/cabnet/public_html/gov.cabnet.app
- Private app folder: /home/cabnet/gov.cabnet.app_app
- Private config folder: /home/cabnet/gov.cabnet.app_config
- SQL folder: /home/cabnet/gov.cabnet.app_sql

Source of truth:
1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. HANDOFF.md and CONTINUE_PROMPT.md.
3. README.md, SCOPE.md, DEPLOYMENT.md, SECURITY.md, docs/, and PROJECT_FILE_MANIFEST.md.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

Critical safety rule:
No live EDXEIX submission has been approved or enabled. Do not add automatic/live submission behavior unless Andreas explicitly requests a live-submit patch after a real eligible future Bolt trip exists and preflight passes.

Default to:
- read-only diagnostics
- dry-run testing
- local-only LAB/test rows
- preflight/preview
- queue visibility
- guarded operations pages
- no live EDXEIX HTTP/form submission

Never submit historical, cancelled, terminal, expired, invalid, LAB/test, or past Bolt orders to EDXEIX.

Current final validated state as of 2026-04-25:
- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Dry-run future booking harness was built and validated end-to-end.
- LAB/local test rows, jobs, and attempts were cleaned up.
- Ops access guard is installed and active through .user.ini and server-only /home/cabnet/gov.cabnet.app_config/ops.php.
- Readiness is clean and currently shows READY_FOR_REAL_BOLT_FUTURE_TEST.
- Future Test Checklist currently shows READY TO CREATE REAL FUTURE TEST RIDE with candidate_count 0.
- Live EDXEIX submission remains disabled and unauthorized.
- Local submission jobs: 0.
- LAB normalized rows: 0.
- Submission attempts: 0.
- Live attempts indicated: 0.

Current mapping state:
- Drivers mapped: 1/2.
- Vehicles mapped: 2/15.
- Filippos Giannakopoulos is mapped to EDXEIX driver ID 17585.
- Georgios Zachariou should remain unmapped for now unless his exact EDXEIX driver ID is independently confirmed.
- Mapped vehicle EMX6874 → EDXEIX vehicle ID 13799.
- Mapped vehicle EHA2545 → EDXEIX vehicle ID 5949.

Known EDXEIX driver reference notes shown in /ops/mappings.php:
- 1658 — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
- 17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
- 6026 — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
These are reference-only notes and do not automatically map any Bolt driver.

Current safe operations pages:
- /ops/index.php — safe read-only operations landing page
- /ops/readiness.php — readiness audit UI
- /ops/future-test.php — real future Bolt test checklist
- /ops/mappings.php — mapping coverage/editor, guarded POST only for EDXEIX ID fields
- /ops/jobs.php — local queue/attempt viewer
- /ops/bolt-live.php — Bolt-side operational view
- /ops/test-booking.php — local LAB dry-run booking harness
- /ops/cleanup-lab.php — LAB/test cleanup utility

Current JSON/diagnostic endpoints:
- /bolt_readiness_audit.php
- /bolt_edxeix_preflight.php?limit=30
- /bolt_jobs_queue.php?limit=50
- /ops/future-test.php?format=json
- /ops/mappings.php?format=json

Ops guard notes:
- Guard file: /home/cabnet/gov.cabnet.app_app/lib/ops_guard.php
- Auto-prepend file: /home/cabnet/public_html/gov.cabnet.app/.user.ini
- Server-only guard config: /home/cabnet/gov.cabnet.app_config/ops.php
- Do not commit gov.cabnet.app_config/ops.php.
- If Andreas is blocked, check his current public IP and update the server-only ops.php allowlist.
- .user.ini may take a few minutes to refresh under cPanel/PHP-FPM.

Recent feature files/docs added:
- gov.cabnet.app_app/src/TestBookingFactory.php
- gov.cabnet.app_app/lib/ops_guard.php
- public_html/gov.cabnet.app/ops/index.php
- public_html/gov.cabnet.app/ops/readiness.php
- public_html/gov.cabnet.app/ops/future-test.php
- public_html/gov.cabnet.app/ops/mappings.php
- public_html/gov.cabnet.app/ops/jobs.php
- public_html/gov.cabnet.app/ops/test-booking.php
- public_html/gov.cabnet.app/ops/cleanup-lab.php
- docs/DRY_RUN_TEST_BOOKING_HARNESS.md
- docs/LAB_TEST_SAFETY_OUTPUT.md
- docs/LAB_DRY_RUN_CLEANUP.md
- docs/OPS_ACCESS_GUARD.md
- docs/MAPPING_COVERAGE_DASHBOARD.md
- docs/MAPPING_JSON_REDACTION.md
- docs/MAPPING_EDITOR.md
- docs/EDXEIX_DRIVER_REFERENCES.md
- docs/REAL_FUTURE_TEST_CHECKLIST.md
- docs/SAFE_OPS_INDEX.md

Important SQL migrations from recent cycle:
- gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
- gov.cabnet.app_sql/2026_04_25_mark_local_dry_run_attempts.sql
- gov.cabnet.app_sql/2026_04_25_lab_cleanup_preview.sql
- gov.cabnet.app_sql/2026_04_25_mapping_update_audit.sql

Do not commit:
- gov.cabnet.app_config/config.php
- gov.cabnet.app_config/bolt.php
- gov.cabnet.app_config/database.php
- gov.cabnet.app_config/app.php
- gov.cabnet.app_config/edxeix.php
- gov.cabnet.app_config/ops.php
- gov.cabnet.app_config/*.local.php
- real API keys, DB passwords, EDXEIX cookies/session files/CSRF tokens, raw SQL data dumps, logs, runtime artifacts, cache files, local zips, or temporary public diagnostics.

Current blocker:
Andreas cannot currently create/schedule a real future Bolt ride because Filippos must be present/available for the test. Do not keep asking him to do this until he says it is possible.

When the real future test becomes possible:
1. Create/schedule one real Bolt test ride at least 40–60 minutes in the future.
2. Prefer driver Filippos Giannakopoulos / EDXEIX 17585.
3. Prefer mapped vehicle EMX6874 / EDXEIX 13799, or EHA2545 / EDXEIX 5949.
4. Run /bolt_sync_orders.php.
5. Run /ops/future-test.php.
6. Run /bolt_edxeix_preflight.php?limit=30.
7. Confirm candidate appears as real, future, non-terminal, mapped, and preflight-ready.
8. Keep live submission disabled unless Andreas explicitly requests a separate live-submit patch.

If Andreas says “continue” before a real future ride exists, choose the safest non-live step, such as:
- update documentation/continuity
- improve read-only dashboard clarity
- add read-only QA checks
- add EDXEIX vehicle reference notes if verified IDs are provided
- add an audit history page for mapping_update_audit
- fix cosmetic JSON percentage formatting
- improve route/link audit tooling

Patch creation rules:
- Inspect first, patch second.
- Prefer small production-safe patches.
- Preserve filenames, routes, includes, database compatibility, and cPanel paths.
- Use mysqli prepared statements and defensive validation.
- Keep public endpoints thin and reusable logic in the private app folder.
- Do not add frameworks, Composer, Node, or heavy dependencies.
- For deployment zips, do not wrap files in an extra package folder.
- Zip root must mirror the live/repository structure directly, e.g. public_html/gov.cabnet.app/..., gov.cabnet.app_app/..., gov.cabnet.app_sql/..., docs/..., HANDOFF.md, CONTINUE_PROMPT.md, PATCH_README.md.
- Include only changed/added files unless Andreas explicitly asks for a full archive.
- Always provide exact upload paths, SQL if any, verification URLs, expected results, Git commit title, and Git commit description.
