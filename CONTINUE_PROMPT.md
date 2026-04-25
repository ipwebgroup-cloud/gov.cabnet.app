# CONTINUE PROMPT — gov.cabnet.app Bolt → EDXEIX Bridge

You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Continue from this exact baseline.

## Critical instruction

Do **not** enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.

The current next safe workstream is the **Bolt API Visibility Diagnostic** and any follow-up analysis from its recorded sanitized snapshots.

## Project identity

- Domain: `https://gov.cabnet.app`
- GitHub repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Do not introduce frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.

Expected cPanel/server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Source-of-truth order

1. Latest uploaded files, pasted code, screenshots, SQL output, SSH output, JSON output, or live audit output in the current chat.
2. `HANDOFF.md` and `CONTINUE_PROMPT.md`.
3. `README.md`, `SCOPE.md`, `DEPLOYMENT.md`, `SECURITY.md`, `docs/`, and `PROJECT_FILE_MANIFEST.md`.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

## Safety rules

- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, or private credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Sanitize downloadable zips. Exclude secrets, logs, sessions, raw data dumps, cache files, and unsafe temporary public diagnostics.
- Patch zips must not include a wrapper folder. Zip root must mirror repository/live structure directly.

## Current project state

The project is at a **pre-live blocked baseline**.

Working/confirmed:

- Ops console exists and is guarded at `https://gov.cabnet.app/ops/`.
- Ops guard is active through `.user.ini` and `/home/cabnet/gov.cabnet.app_app/lib/ops_guard.php`.
- EDXEIX session capture via Firefox extension works.
- EDXEIX cookie/CSRF session file is saved server-side and never printed.
- EDXEIX session readiness previously showed ready.
- Live submit gate exists at `https://gov.cabnet.app/ops/live-submit.php`.
- Live HTTP transport remains intentionally blocked.
- `live_submit_enabled` is false.
- `http_submit_enabled` is false.
- No live EDXEIX submission has been performed.

Known driver IDs:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

Known first-test mapping:

```text
Filippos Giannakopoulos
Bolt UUID: 57256761-d21b-4940-a3ca-bdcec5ef6af1
EDXEIX driver ID: 17585

EMX6874
EDXEIX vehicle ID: 13799

EHA2545
EDXEIX vehicle ID: 5949
```

Leave Georgios Zachariou unmapped unless his exact EDXEIX driver ID is independently confirmed.

## Important 2026-04-25 Bolt test result

A real Bolt rider-app test with Filippos and EMX6874 showed:

```text
Ride accepted / assigned: orders_seen: 0
Passenger picked up / waiting: orders_seen: 0
Trip started: orders_seen: 0
Trip completed: orders_seen: 1
```

After completion:

```text
orders_seen: 1
action: inserted
id: 11
external_order_id: MjI3Mi0yNDgwMjc1MjQ5LTMyMzIxMTY1OTI
edxeix_ready: 1
```

Conclusion: the current Bolt Fleet orders endpoint did not expose the active trip before completion during that test.

## New patch to deploy/analyze

Patch name:

```text
Bolt API Visibility Diagnostic
```

Files:

```text
gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php
public_html/gov.cabnet.app/ops/bolt-api-visibility.php
public_html/gov.cabnet.app/ops/bolt-api-visibility-run.php
docs/BOLT_API_VISIBILITY_DIAGNOSTIC.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

New URLs:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php
https://gov.cabnet.app/ops/bolt-api-visibility-run.php
```

The diagnostic:

- calls the existing Bolt order sync path in dry-run mode only;
- does not submit to EDXEIX;
- does not stage jobs;
- prints sanitized summaries only;
- optionally records sanitized private JSONL timeline rows to:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/bolt-api-visibility/YYYY-MM-DD.jsonl
```

## First verification after upload

Open:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php
```

Run:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=0&hours_back=24&sample_limit=20
```

Expected:

- Page loads in guarded ops area.
- It shows `EDXEIX LIVE SUBMIT OFF`, `BOLT DRY-RUN PROBE`, and `SANITIZED SUMMARY ONLY`.
- It displays `orders_seen` without enabling live submission.

## Recommended next live test

When Filippos and EMX6874 are available, use:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=1&hours_back=24&sample_limit=20&watch_driver_uuid=57256761-d21b-4940-a3ca-bdcec5ef6af1&watch_vehicle_plate=EMX6874&label=filippos-emx6874-probe
```

Or auto-refresh every 20 seconds:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=1&hours_back=24&sample_limit=20&watch_driver_uuid=57256761-d21b-4940-a3ca-bdcec5ef6af1&watch_vehicle_plate=EMX6874&label=filippos-emx6874-probe&refresh=20
```

Capture one recorded snapshot at each stage:

1. accepted/assigned
2. pickup/waiting
3. trip started
4. completed

Then analyze whether the endpoint still returns `orders_seen: 0` until completion.

## Commit metadata

Title:

```text
Add Bolt API visibility diagnostic
```

Description:

```text
Adds a guarded, read-only Bolt API visibility diagnostic for the gov.cabnet.app Bolt → EDXEIX bridge. The new ops page and JSON endpoint probe the existing Bolt order sync path in dry-run mode only, summarize sanitized visibility snapshots, optionally record a private JSONL timeline, and provide a repeatable workflow for confirming whether active Bolt trips are visible before completion. No EDXEIX live submission, EDXEIX HTTP transport, queue staging, SQL changes, secrets, raw payloads, cookies, or session data are introduced.
```
