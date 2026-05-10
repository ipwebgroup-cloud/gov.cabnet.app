# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current Version

v6.6.2 — Manual Bolt pre-ride email utility added for immediate operations fallback.

## Project Identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Server layout:
  - `/home/cabnet/public_html/gov.cabnet.app`
  - `/home/cabnet/gov.cabnet.app_app`
  - `/home/cabnet/gov.cabnet.app_config`
  - `/home/cabnet/gov.cabnet.app_sql`

## Workflow

All future file deliverables must be zip packages. Andreas downloads the zip, extracts it locally into the GitHub Desktop repo, uploads manually to the server, tests production, then commits through GitHub Desktop.

## Production Safety Status

- EDXEIX live submission remains disabled.
- `submission_jobs` must remain zero unless Andreas explicitly approves live testing.
- `submission_attempts` must remain zero unless Andreas explicitly approves live testing.
- AADE invoice issuing is active only through:

```text
/home/cabnet/gov.cabnet.app_app/cli/bolt_pickup_receipt_worker.php
```

- Pre-ride Bolt email intake is EDXEIX preparation/context only.
- Pre-ride Bolt email must never issue AADE invoices.
- Manual AADE send is blocked.
- Mail/auto dry-run AADE issue paths are no-op/blocked.

## v6.6.2 Manual Pre-Ride Email Utility

Purpose: keep business operations moving while full normalized automation is guarded.

Web utility:

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

Files:

```text
/home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
/home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php
/home/cabnet/docs/BOLT_PRE_RIDE_EMAIL_UTILITY.md   # repo docs path: docs/BOLT_PRE_RIDE_EMAIL_UTILITY.md
```

Safety:

- No database access.
- No database writes.
- No network calls.
- No Bolt API calls.
- No EDXEIX calls.
- No AADE calls.
- No queue jobs.
- No submission attempts.
- No email body storage.

Usage:

1. Paste the full Bolt pre-ride email body into `/ops/pre-ride-email-tool.php`.
2. Press **Parse email**.
3. Review missing fields/warnings/confidence.
4. Edit any field that looks wrong.
5. Copy individual fields, the dispatch summary, or the CSV row for manual operations.

CLI verification:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
php -l /home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php --file=/tmp/bolt-email.txt --json
```

## Correct Source Split

### EDXEIX

```text
Pre-ride Bolt email
→ manual parser / eventual bolt_mail_intake
→ mail-derived normalized local preflight booking
→ EDXEIX readiness / browser-fill / eventual one-shot live submit
```

EDXEIX submission data source is strictly the pre-ride Bolt email, not the Bolt API pickup/finalized data.

### AADE

```text
Bolt API pickup timestamp
→ bolt_pickup_receipt_worker.php
→ AADE invoice issue
```

AADE invoice source is strictly the Bolt API pickup timestamp worker path.

## Important AADE Incident

Duplicate AADE receipts were observed for same logical trips:

- Liam Bradbury: bookings 83 and 85, same route within approximately 5 minutes.
- Elizabeth Brokou: bookings 68 and 69, same route within approximately 6 minutes.

v6.5.2 corrected the production posture by restricting AADE issuing to the Bolt API pickup timestamp worker only and hardening duplicate/source guards.

## Bolt API Timing Evidence

A live ride was completed after v6.5.2. The receipt was sent when the ride concluded.

Monitoring did not find:

```text
PROOF_CANDIDATE_PICKUP_BEFORE_FINISH
```

Do not claim certainty that Bolt exposes `order_pickup_timestamp` before ride finish.

Preferred wording:

```text
AADE invoice is issued only through the Bolt API pickup timestamp path, subject to when Bolt exposes that timestamp.
```

## EDXEIX v6.6.1 Readiness Report

Main CLI:

```text
/home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
```

Safety:

- Read-only.
- Does not call EDXEIX.
- Does not issue AADE.
- Does not create `submission_jobs`.
- Does not create `submission_attempts`.
- Does not print session cookies or CSRF tokens.

Verification:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
```

Expected safe result:

```text
ok: true
source_policy.edxeix_submission_source: pre_ride_bolt_email_only
source_policy.aade_invoice_source: bolt_api_pickup_timestamp_worker_only
queues_unchanged: true
```

## Next Safe Tasks

1. Upload and verify v6.6.2 manual pre-ride email utility on production.
2. Use it operationally as a manual helper while the business needs immediate function.
3. Continue the main normalized mail intake only after operations are stable.
4. Wait for a real future pre-ride Bolt email candidate.
5. Use readiness report to confirm `preflight_ready=true` for a mail-derived normalized booking.
6. Only then run `live_submit_one_booking.php --analyze-only` for that exact booking.
7. Do not enable live EDXEIX submission unless Andreas explicitly asks.
