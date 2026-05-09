# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current Version

v6.5.3 documentation sync for confirmed v6.5.2 production posture.

The latest code commit confirmed in GitHub is:

```text
79f86ac — Restore AADE pickup-swipe-only receipt flow and harden duplicate guards
```

## Project Identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- No frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.

## Server Layout

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Deployment / Commit Workflow

The live server is not a cloned Git repo.

Workflow:

1. Code with ChatGPT.
2. Download a zip package.
3. Extract locally into the GitHub Desktop repo.
4. Upload manually to the server when needed.
5. Test on the server.
6. Commit through GitHub Desktop after production confirmation.

All future deliverables should be zip packages. The zip root must mirror the repository/server structure directly and must not include a wrapper folder.

## Source-of-Truth Priority

1. Latest pasted terminal output, screenshots, uploaded files, SQL output, and live audit output in the current chat.
2. HANDOFF.md / CONTINUE_PROMPT.md.
3. README.md, SCOPE.md, DEPLOYMENT.md, SECURITY.md, docs/, and PROJECT_FILE_MANIFEST.md.
4. GitHub repo.
5. Prior memory only as background, never proof of current code state.

## Critical Safety Rules

- Never request or expose real API keys, DB passwords, tokens, cookies, AADE credentials, session files, or private config.
- EDXEIX live submission must remain disabled unless Andreas explicitly asks for live-submit activation.
- EDXEIX `submission_jobs` and `submission_attempts` must remain zero unless explicitly approved.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- AADE receipt issuing is production-sensitive.
- AADE invoices must only issue from the Bolt API pickup timestamp worker path.
- Pre-ride Bolt email is preparation/context only and must not issue AADE invoices.
- Manual AADE send is blocked.
- Mail/auto dry-run AADE issue paths are blocked/no-op.
- Keep changes small, production-safe, inspect-first, patch-second.
- Preserve plain PHP/mysqli/cPanel paths and workflow.

## Confirmed Production State

AADE invoices are active only through:

```text
/home/cabnet/gov.cabnet.app_app/cli/bolt_pickup_receipt_worker.php
```

Root cron runs the pickup worker:

```text
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_pickup_receipt_worker.php --minutes=240 --limit=20 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_pickup_receipts.log 2>&1
```

Emergency lock is absent:

```text
/home/cabnet/gov.cabnet.app_app/storage/runtime/aade_receipts_DISABLED.lock
```

Pre-ride email intake is working as preparation/context:

```text
/home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php
```

Blocked/no-op AADE issue paths:

```text
/home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
/home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php
/home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --send
```

## v6.5.2 Code Commit Summary

Commit title:

```text
Restore AADE pickup-swipe-only receipt flow and harden duplicate guards
```

Confirmed changed files:

```text
gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php
gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php
gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
gov.cabnet.app_app/cli/bolt_pickup_receipt_worker.php
gov.cabnet.app_app/src/Mail/BoltMailAutoDryRunService.php
gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
public_html/gov.cabnet.app/edxeix-extension-payload.php
public_html/gov.cabnet.app/ops/aade-receipt-payload.php
public_html/gov.cabnet.app/ops/edxeix-extension-payload.php
tools/firefox-edxeix-session-capture/manifest.json
tools/firefox-edxeix-session-capture/popup.html
tools/firefox-edxeix-session-capture/popup.js
```

## Important AADE Incident

Duplicate AADE receipts were observed for same logical trips:

- Liam Bradbury: bookings 83 and 85, same route within approximately 5 minutes, two AADE marks.
- Elizabeth Brokou: bookings 68 and 69, same route within approximately 6 minutes, two AADE marks.

Emergency no-op locks were applied, then the production-safe solution was changed to pickup timestamp worker-only AADE issuing.

## Current AADE Issuing Rule

AADE receipts may only be issued by the pickup timestamp worker path.

Central guard file:

```text
/home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
```

Guards include:

- Emergency lock guard.
- Only pickup-worker created_by source can issue.
- Logical duplicate receipt guard for same customer + route + pickup window.
- Test/synthetic/invalid booking blocks.
- No EDXEIX jobs or attempts created by AADE receipt flow.

## Bolt API Pickup Timestamp Evidence

A live ride was completed after v6.5.2. The receipt was sent when the ride concluded.

Monitoring did not find:

```text
PROOF_CANDIDATE_PICKUP_BEFORE_FINISH
```

Logs repeatedly showed:

```text
waiting_for_real_non_empty_pickup_timestamp_before_finish...
```

Do not claim certainty that Bolt exposes `order_pickup_timestamp` before ride finish.

Preferred wording:

```text
AADE invoice is issued only through the Bolt API pickup timestamp path, subject to when Bolt exposes that timestamp.
```

Current observed behavior suggests this may occur near or after ride conclusion.

## EDXEIX Current Status

EDXEIX is in pre-live / browser-assisted readiness mode.

Active/safe:

```text
Bolt data → normalized booking → EDXEIX preflight/audit → browser-fill payload preview
```

Not active:

```text
Automatic EDXEIX live submit
Automatic EDXEIX queue creation
Automatic EDXEIX API posting
Bulk EDXEIX production submission
```

Queues must remain zero unless Andreas explicitly approves live EDXEIX work:

```sql
SELECT COUNT(*) AS submission_jobs FROM submission_jobs;
SELECT COUNT(*) AS submission_attempts FROM submission_attempts;
```

## Important EDXEIX Files

```text
public_html/gov.cabnet.app/edxeix-extension-payload.php
public_html/gov.cabnet.app/ops/edxeix-extension-payload.php
public_html/gov.cabnet.app/bolt_edxeix_preflight.php
public_html/gov.cabnet.app/bolt_readiness_audit.php
public_html/gov.cabnet.app/bolt_stage_edxeix_jobs.php
public_html/gov.cabnet.app/bolt_submission_worker.php
public_html/gov.cabnet.app/ops/submit.php
public_html/gov.cabnet.app/ops/jobs.php
public_html/gov.cabnet.app/ops/readiness.php
gov.cabnet.app_app/cli/edxeix_prelive_audit.php
gov.cabnet.app_app/cli/live_submit_one_booking.php
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
tools/firefox-edxeix-session-capture/
```

## Verification Commands

```bash
ls -l /home/cabnet/gov.cabnet.app_app/storage/runtime/aade_receipts_DISABLED.lock 2>/dev/null || echo "OK: no emergency lock"

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=85 --send --confirm='TEST' --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_pickup_receipt_worker.php --minutes=240 --limit=20 --dry-run --json

crontab -l | grep -E "bolt_pickup_receipt_worker|bolt_mail_receipt_worker|auto_bolt_mail_dry_run|aade_mydata_receipt_payload" || true

tail -n 120 /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_pickup_receipts.log

mysql cabnet_gov -e "
SELECT id,intake_id,normalized_booking_id,provider_status,http_status,total_amount,mark,created_by,created_at
FROM receipt_issuance_attempts
WHERE provider='aade_mydata'
ORDER BY id DESC
LIMIT 20;
"

mysql cabnet_gov -e "
SELECT COUNT(*) AS submission_jobs FROM submission_jobs;
SELECT COUNT(*) AS submission_attempts FROM submission_attempts;
"

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php --future-hours=24 --limit=20 --only-candidates --json
```

## Next Safe Tasks

1. Commit this documentation sync so repo continuity matches v6.5.2 code state.
2. Create a read-only reusable CLI audit/report for Bolt pickup timestamp timing.
3. Improve EDXEIX readiness reporting without DB writes, queue creation, or live submit.
4. Keep EDXEIX disabled unless Andreas explicitly asks for live-submit activation.
