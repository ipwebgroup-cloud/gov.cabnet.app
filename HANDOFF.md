# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project identity

- Domain: `https://gov.cabnet.app`
- GitHub repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Safety rule

No live EDXEIX submission has been approved or enabled. Do not add automatic/live submission behavior unless Andreas explicitly requests a live-submit patch after a real eligible future Bolt trip exists and preflight passes.

Default posture must remain:

- read-only diagnostics
- dry-run testing
- local-only LAB/test rows
- preflight/preview
- queue visibility
- guarded operations pages
- no live EDXEIX HTTP/form submission

Historical, cancelled, terminal, expired, invalid, LAB/test, or past Bolt orders must never be submitted to EDXEIX.

## Source-of-truth order for future chats

1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. This `HANDOFF.md` and `CONTINUE_PROMPT.md`.
3. `README.md`, `SCOPE.md`, `DEPLOYMENT.md`, `SECURITY.md`, `docs/`, and `PROJECT_FILE_MANIFEST.md`.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

## Current final validated state — 2026-04-25

The project is clean and safe after the recent development cycle.

Validated outcomes:

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Dry-run future booking harness was created and tested end-to-end.
- LAB/local future booking was created, staged locally, dry-run worker attempt recorded, and then cleaned up.
- LAB/test cleanup tool was validated.
- Ops access guard was installed and verified.
- Mapping coverage dashboard/editor was installed and verified.
- Mapping JSON output was sanitized and no longer exposes `raw_payload_json`.
- Known EDXEIX driver reference panel was added.
- Real Future Bolt Test Checklist was added.
- Legacy `/ops/index.php` was replaced by a safe read-only operations landing page.
- Readiness is clean and currently suitable for a real future Bolt preflight test only.

Latest observed readiness state:

```text
Verdict: READY_FOR_REAL_BOLT_FUTURE_TEST
LAB normalized rows: 0
Staged LAB jobs: 0
Submission attempts total: 0
Local submission jobs: 0
Live attempts indicated: 0
Current real future submission-safe candidates: 0
```

Latest observed future-test state:

```text
Status: READY TO CREATE REAL FUTURE TEST RIDE
Real future candidates: 0
Driver mappings ready: 1/2
Vehicle mappings ready: 2/15
Live submission authorization: 0
```

This means the system is clean and waiting for a real future Bolt trip. It does **not** mean live EDXEIX submission is enabled.

## Current mapping state

Driver mappings:

```text
1/2 drivers mapped
```

Known mapped driver:

```text
Filippos Giannakopoulos → EDXEIX driver_id 17585
```

Known unmapped driver:

```text
Georgios Zachariou → leave unmapped for now unless his exact EDXEIX driver ID is independently confirmed.
```

Known EDXEIX driver reference values shown in `/ops/mappings.php`:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

These are reference-only notes and do not automatically map any Bolt driver.

Vehicle mappings:

```text
2/15 vehicles mapped
```

Known mapped vehicles:

```text
EMX6874 → EDXEIX vehicle_id 13799
EHA2545 → EDXEIX vehicle_id 5949
```

## Current safe operations pages

Primary console:

```text
https://gov.cabnet.app/ops/index.php
```

Main guarded workflow tools:

```text
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/jobs.php
https://gov.cabnet.app/ops/bolt-live.php
https://gov.cabnet.app/ops/test-booking.php
https://gov.cabnet.app/ops/cleanup-lab.php
```

JSON/diagnostic endpoints:

```text
https://gov.cabnet.app/bolt_readiness_audit.php
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
https://gov.cabnet.app/bolt_jobs_queue.php?limit=50
https://gov.cabnet.app/ops/future-test.php?format=json
https://gov.cabnet.app/ops/mappings.php?format=json
```

All operations and diagnostic endpoints are protected by the ops access guard.

## Ops access guard

The guard is loaded by:

```text
/home/cabnet/public_html/gov.cabnet.app/.user.ini
```

Expected content:

```ini
auto_prepend_file=/home/cabnet/gov.cabnet.app_app/lib/ops_guard.php
```

Guard library:

```text
/home/cabnet/gov.cabnet.app_app/lib/ops_guard.php
```

Server-only config:

```text
/home/cabnet/gov.cabnet.app_config/ops.php
```

Important:

- `gov.cabnet.app_config/ops.php` must remain server-only.
- It is ignored by Git.
- Do not commit real allowed IPs, tokens, or cookie secrets.
- If Andreas is blocked, check the current public IP and update server-only `ops.php`.
- `.user.ini` may take a few minutes to refresh under cPanel/PHP-FPM.

Expected permissions:

```text
/home/cabnet/gov.cabnet.app_config/ops.php             640 cabnet:cabnet
/home/cabnet/gov.cabnet.app_app/lib/ops_guard.php      644 cabnet:cabnet
/home/cabnet/public_html/gov.cabnet.app/.user.ini      644 cabnet:cabnet
```

## Current important files

Private app:

```text
gov.cabnet.app_app/lib/bolt_sync_lib.php
gov.cabnet.app_app/lib/ops_guard.php
gov.cabnet.app_app/src/TestBookingFactory.php
```

Public operations/endpoints:

```text
public_html/gov.cabnet.app/ops/index.php
public_html/gov.cabnet.app/ops/readiness.php
public_html/gov.cabnet.app/ops/future-test.php
public_html/gov.cabnet.app/ops/mappings.php
public_html/gov.cabnet.app/ops/jobs.php
public_html/gov.cabnet.app/ops/bolt-live.php
public_html/gov.cabnet.app/ops/test-booking.php
public_html/gov.cabnet.app/ops/cleanup-lab.php
public_html/gov.cabnet.app/bolt_sync_reference.php
public_html/gov.cabnet.app/bolt_sync_orders.php
public_html/gov.cabnet.app/bolt_edxeix_preflight.php
public_html/gov.cabnet.app/bolt_stage_edxeix_jobs.php
public_html/gov.cabnet.app/bolt_submission_worker.php
public_html/gov.cabnet.app/bolt_jobs_queue.php
public_html/gov.cabnet.app/bolt_readiness_audit.php
```

SQL migrations added during recent cycle:

```text
gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
gov.cabnet.app_sql/2026_04_25_mark_local_dry_run_attempts.sql
gov.cabnet.app_sql/2026_04_25_lab_cleanup_preview.sql
gov.cabnet.app_sql/2026_04_25_mapping_update_audit.sql
```

Documentation added/updated during recent cycle:

```text
docs/DRY_RUN_TEST_BOOKING_HARNESS.md
docs/LAB_TEST_SAFETY_OUTPUT.md
docs/LAB_DRY_RUN_CLEANUP.md
docs/OPS_ACCESS_GUARD.md
docs/MAPPING_COVERAGE_DASHBOARD.md
docs/MAPPING_JSON_REDACTION.md
docs/MAPPING_EDITOR.md
docs/EDXEIX_DRIVER_REFERENCES.md
docs/REAL_FUTURE_TEST_CHECKLIST.md
docs/SAFE_OPS_INDEX.md
```

## Do not commit

Never commit:

```text
gov.cabnet.app_config/config.php
gov.cabnet.app_config/bolt.php
gov.cabnet.app_config/database.php
gov.cabnet.app_config/app.php
gov.cabnet.app_config/edxeix.php
gov.cabnet.app_config/ops.php
gov.cabnet.app_config/*.local.php
```

Also do not commit:

```text
real API keys
DB passwords
EDXEIX cookies/session files/CSRF tokens
raw SQL data dumps
logs
runtime artifacts
cache files
public temporary diagnostics
local zip archives
```

## Next real operational step

Andreas cannot currently create a real future Bolt ride because Filippos must be physically available/present to do that test.

When possible, the next real operational test should be:

```text
Create/schedule one real Bolt test ride at least 40–60 minutes in the future.
Preferred driver: Filippos Giannakopoulos / EDXEIX 17585
Preferred mapped vehicle: EMX6874 / EDXEIX 13799
Alternative mapped vehicle: EHA2545 / EDXEIX 5949
```

Then run:

```text
https://gov.cabnet.app/bolt_sync_orders.php
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
```

Expected before live submission is even considered:

```text
/ops/future-test.php → REAL FUTURE CANDIDATE READY FOR PREFLIGHT
/bolt_edxeix_preflight.php → one real Bolt row technically/preflight ready
Live EDXEIX submission → still disabled
```

## If continuing before a real future ride exists

Prefer safe, non-live tasks only:

1. Improve documentation and handoff continuity.
2. Improve dashboard clarity.
3. Add more read-only QA checks.
4. Add more EDXEIX vehicle reference notes if Andreas provides verified IDs.
5. Fix minor cosmetic output issues, such as JSON percentage formatting.
6. Do not add live submission behavior.

## Recommended future optional improvements

- Add EDXEIX vehicle reference panel similar to the driver references panel if verified vehicle IDs become available.
- Format JSON percentage values as strings to avoid long floating-point display in browser JSON viewers.
- Add a read-only audit history page for `mapping_update_audit`.
- Add a route/link audit page for ops tools.
- Add a deployment/package manifest page.

## Patch packaging rule

When creating deployment patch zips:

- Do not wrap files in a package folder like `gov_patch_name/`.
- The zip root must mirror live/repository structure directly.
- Correct zip root examples:

```text
public_html/gov.cabnet.app/...
gov.cabnet.app_app/...
gov.cabnet.app_config_examples/...
gov.cabnet.app_sql/...
docs/...
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

- For cPanel/manual upload patches, include only changed/added files unless Andreas explicitly asks for a full archive.
- Always show or internally verify the zip file tree before final delivery.
