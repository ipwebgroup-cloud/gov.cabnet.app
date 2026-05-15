# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project identity

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- No frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves

## Server layout

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

## Workflow

1. Code with Sophion.
2. Download zip patch/package.
3. Extract into local GitHub Desktop repo.
4. Upload manually to server.
5. Test on server.
6. Commit via GitHub Desktop after production confirmation.

## Source-of-truth order

1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. HANDOFF.md and CONTINUE_PROMPT.md.
3. README.md, SCOPE.md, DEPLOYMENT.md, SECURITY.md, docs/, PROJECT_FILE_MANIFEST.md.
4. GitHub repo.
5. Prior memory/context only as background.

## Critical safety rules

- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, or private credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- V0 / existing production workflows must remain untouched unless Andreas explicitly requests otherwise.

## Latest milestone — V3 real-mail observation, v3.1.0–v3.1.4

The V3 real-mail observation milestone is documented in:

```text
docs/V3_REAL_MAIL_OBSERVATION_MILESTONE_20260515.md
```

### Verified checks

V3 real-mail queue health:

```text
queue_health_ok=true
possible_real=12
future_active=0
live_risk=false
final_blocks=[]
```

V3 expiry reason audit alignment:

```text
ok=true
version=v3.1.4-v3-real-mail-expiry-audit-alignment
possible_real=12
possible_real_expired=11
possible_real_non_expired=1
mapping_correction=1
mismatch_explained=true
live_risk=false
final_blocks=[]
```

### Interpretation

```text
No eligible future V3 row.
No live-submit-ready V3 row.
No dry-run-ready V3 row.
No EDXEIX submission recommended.
V3 live gate remains closed.
```

### Files involved

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Previous milestone — Legacy public utility audit, v3.0.80–v3.0.99

Completed and committed:

```text
Navigation de-bloat
Public route exposure audit
Public utility relocation plan
Reference cleanup planning
Legacy public utility wrapper
Legacy utility usage audit
Quiet-period audit
Stats-source audit
Legacy utility readiness board
```

No routes were moved, deleted, or redirected.

## Current safety posture

```text
Production Pre-Ride Tool: untouched
V0 workflow: untouched
Queue mutations: none
DB writes: none
SQL changes: none
Bolt calls: none
EDXEIX calls: none
AADE calls: none
Live EDXEIX submit: disabled
V3 live gate: closed
```

## Recommended next safest step

Build a read-only “next eligible future real-mail watcher” for V3. It should highlight only future possible-real rows and show why each is or is not eligible. It must not submit, approve, mutate queue status, or open the live gate.
