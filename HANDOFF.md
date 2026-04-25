# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

_Last refreshed: 2026-04-25_

You are continuing development of **gov.cabnet.app**, a plain PHP + mysqli/MariaDB cPanel project that bridges Bolt Fleet API data into normalized local bookings and prepares them for EDXEIX lease-agreement preflight/queue/live-submit workflow.

## Project identity

- Domain: `https://gov.cabnet.app`
- GitHub repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Do **not** introduce frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Source-of-truth order for the next chat

1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. This `HANDOFF.md` and `CONTINUE_PROMPT.md`.
3. `README.md`, `SCOPE.md`, `DEPLOYMENT.md`, `SECURITY.md`, `docs/`, and `PROJECT_FILE_MANIFEST.md`.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

## Current final baseline

The system is in a strong safe-pre-live state.

Confirmed current state from `/ops/edxeix-session.php?format=json` and SSH checks:

```text
EDXEIX submit URL configured: yes
EDXEIX submit URL host: edxeix.yme.gov.gr
EDXEIX form method: POST
EDXEIX server-side session file exists/readable/valid: yes
Cookie raw present: yes
CSRF raw present: yes
Cookie present and placeholder-free: yes
CSRF present and placeholder-free: yes
cookie_length: 578
csrf_length: 40
session source: firefox_extension_capture_fixed_url_no_phrase
Session ready: yes
Live submit enabled: false
HTTP submit enabled: false
Overall ready for final live patch prerequisites: true
```

Confirmed from `/ops/live-submit.php`:

```text
EDXEIX URL configured: yes
EDXEIX session ready: yes
Real future candidates: 0
Live-eligible rows: 0
Live HTTP execution: no
Live HTTP transport: intentionally blocked in current patch
```

The remaining expected blockers are:

```text
live_submit_config_disabled
http_submit_config_disabled
no_real_future_candidate
no_selected_real_future_candidate
http_transport_not_enabled_in_this_patch
```

## Installed operator tools

### Operations Console

- URL: `https://gov.cabnet.app/ops/`
- Read-only landing page linking the current guarded workflow tools.

### Readiness Audit

- URL: `https://gov.cabnet.app/ops/readiness.php`
- JSON: `https://gov.cabnet.app/bolt_readiness_audit.php`
- Confirms config/schema/mapping/LAB/job/attempt safety.

### Future Test Checklist

- URL: `https://gov.cabnet.app/ops/future-test.php`
- Read-only checklist for the next real Bolt future-ride test.
- Does not submit to EDXEIX.

### Mapping Coverage / Editor

- URL: `https://gov.cabnet.app/ops/mappings.php`
- GET is read-only.
- POST updates are limited to EDXEIX ID fields and local audit rows.
- Sanitized JSON excludes raw payloads.

Known EDXEIX driver references:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ      — reference only
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ — mapped to Filippos
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ       — reference only
```

Current operating note: leave Georgios Zachariou unmapped for now unless his exact EDXEIX driver ID is independently confirmed.

### Local Test Booking / LAB Cleanup

- `https://gov.cabnet.app/ops/test-booking.php`
- `https://gov.cabnet.app/ops/cleanup-lab.php`
- LAB rows are explicitly marked never-live-submit.
- Cleanup tool removes local LAB/test booking/job/attempt data only.

### EDXEIX Session Readiness

- URL: `https://gov.cabnet.app/ops/edxeix-session.php`
- JSON: `https://gov.cabnet.app/ops/edxeix-session.php?format=json`
- Now diagnostic/read-only for operators except the `Clear Saved EDXEIX Session` action.
- Manual Cookie/CSRF input fields were removed to prevent confusion.
- Normal session refresh is done through the private Firefox extension.
- The page never prints cookie or CSRF values.
- It never calls EDXEIX.
- It never enables live submission.

### Firefox EDXEIX Session Capture Extension

Local repo path:

```text
tools/firefox-edxeix-session-capture/
```

Current extension behavior:

```text
1. Operator logs in to EDXEIX.
2. Operator opens https://edxeix.yme.gov.gr/dashboard/lease-agreement/create.
3. Operator clicks CABnet EDXEIX Capture Firefox extension.
4. Extension captures:
   - hidden _token CSRF value
   - EDXEIX cookies via Firefox cookies API
   - fixed submit URL: https://edxeix.yme.gov.gr/dashboard/lease-agreement
5. Operator clicks Save to gov.cabnet.app.
6. Server saves cookie/CSRF to the runtime session file.
7. Server keeps live_submit_enabled=false and http_submit_enabled=false.
```

Current extension version observed working: `0.1.2`.

Server endpoint:

```text
https://gov.cabnet.app/ops/edxeix-session-capture.php
```

Endpoint behavior:

```text
POST only for saving
GET is diagnostic
confirmation_phrase_required: false
fixed_submit_url: https://edxeix.yme.gov.gr/dashboard/lease-agreement
prints_secrets: false
calls_edxeix: false
calls_bolt: false
writes_database: false
live flags forced disabled: true
```

### Clear Saved EDXEIX Session

`/ops/edxeix-session.php` includes a browser-confirmed button:

```text
Clear Saved EDXEIX Session
```

This clears only the saved server-side EDXEIX Cookie/CSRF runtime session. It does **not** log out of EDXEIX, does **not** remove the configured submit URL, and does **not** enable live submission.

Expected after clear:

```text
Session cookie/CSRF ready: no
Submit URL configured: yes
Live flag: disabled
HTTP flag: disabled
```

Refresh again with the Firefox extension.

### Live EDXEIX Submit Gate

- URL: `https://gov.cabnet.app/ops/live-submit.php`
- JSON: `https://gov.cabnet.app/ops/live-submit.php?format=json`
- Shows global EDXEIX session state independently from candidate-specific Bolt readiness.
- Live HTTP transport is intentionally not implemented/enabled in the current patch.
- It must not submit historical, cancelled, terminal, expired, invalid, LAB/local, or past rows.

## Current database/runtime notes

- `normalized_bookings` has LAB/test safety flags, including:
  - `is_test_booking`
  - `never_submit_live`
  - `test_booking_created_by`
- `submission_jobs` and `submission_attempts` exist.
- Local LAB dry-run rows were cleaned; readiness shows no LAB/test debris.
- `edxeix_live_submission_audit` table exists.
- No successful live EDXEIX submission has been performed by the app.

## Current hard safety rules

These must remain true unless Andreas explicitly approves a final live-submit patch:

```text
live_submit_enabled = false
http_submit_enabled = false
Live HTTP transport blocked/not implemented
No automatic live EDXEIX submission
No LAB/local/test booking live submission
No historical/finished/cancelled/terminal/past booking live submission
No real credentials/secrets/session files committed to Git
No raw cookie/CSRF/token output to browser or chat
```

Real config/session files must remain server-only and ignored by Git:

```text
/home/cabnet/gov.cabnet.app_config/live_submit.php
/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

## What is still required before final live EDXEIX submission

The app cannot proceed to an actual live EDXEIX submission until:

1. Filippos is available to create/schedule one real future Bolt ride.
2. The ride is at least 40–60 minutes in the future, and must pass the configured future guard.
3. The ride syncs from Bolt into normalized bookings.
4. Future Test Checklist shows a real future candidate.
5. Preflight JSON shows the selected row is technically valid.
6. Candidate is not LAB/test/local, not historical, not terminal, not cancelled, not expired, not past.
7. Driver and vehicle mappings are present.
8. EDXEIX session is fresh/ready via Firefox extension.
9. Duplicate protection confirms there is no previous successful live submission for the booking/payload.
10. Andreas explicitly approves the final one-shot live-submit transport patch.
11. Only then may `live_submit_enabled` and `http_submit_enabled` be toggled in server-only config for the approved test.

## Immediate next step when Filippos is available

1. Refresh EDXEIX session via Firefox extension.
2. Create/schedule one real Bolt future ride with Filippos and a mapped vehicle, ideally 40–60 minutes in the future.
3. Open:

```text
https://gov.cabnet.app/ops/future-test.php
```

4. Confirm real future candidate count becomes `1`.
5. Open:

```text
https://gov.cabnet.app/bolt_edxeix_preflight.php
https://gov.cabnet.app/ops/live-submit.php
```

6. Confirm only expected final blockers remain:

```text
live_submit_config_disabled
http_submit_config_disabled
http_transport_not_enabled_in_this_patch
```

7. Do not patch live transport until Andreas explicitly says to proceed with final live EDXEIX submit test.

## Recommended next development task before Filippos is available

If continuing without a real Bolt future ride, the next safe task is documentation/UX only:

- Add/update an operator printable checklist for:
  - refreshing EDXEIX session with Firefox extension
  - scheduling real Bolt ride
  - running Future Test
  - reviewing Preflight
  - verifying Live Submit Gate remains blocked until final approval

Do not enable live transport early.

## Git commit suggestion for this handoff refresh

Title:

```text
Refresh final pre-live handoff baseline
```

Description:

```text
Refreshes HANDOFF.md, CONTINUE_PROMPT.md, and documentation with the current gov.cabnet.app Bolt → EDXEIX bridge pre-live baseline.

The updated handoff records that the Firefox EDXEIX session capture extension is working, EDXEIX submit URL and cookie/CSRF prerequisites are ready, manual session input has been removed, and Clear Saved EDXEIX Session is available.

Live EDXEIX HTTP transport remains intentionally blocked and no live submission behavior is introduced. The next major dependency remains creating a real future Bolt ride with Filippos and a mapped vehicle before any final one-shot live-submit transport patch can be considered.
```
