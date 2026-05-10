# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current Version

v6.6.3 — Manual Bolt pre-ride email utility now includes an EDXEIX browser-side autofill helper.

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

## Current ASAP Business Utility

Web page:

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

Files:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
/home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
/home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php
```

Purpose:

- Paste a real Bolt pre-ride email.
- Parse transfer fields.
- Populate an editable operator form.
- Generate dispatch summary and CSV row.
- Generate a copyable EDXEIX autofill script.

v6.6.3 added the **EDXEIX autofill helper**:

1. Paste/parse the real Bolt email.
2. Review and correct the editable operator form.
3. Click **Copy EDXEIX autofill script**.
4. Open the logged-in EDXEIX rental contract page.
5. Press F12 → Console → paste script → Enter.
6. Verify every populated EDXEIX field manually.
7. Save/submit inside EDXEIX only after human verification.

The helper is browser-side only. It does not submit the form and does not call EDXEIX from gov.cabnet.app.

## Correct Source Split

### EDXEIX

```text
Pre-ride Bolt email
→ manual parser/autofill utility now
→ future bolt_mail_intake / normalized local preflight booking
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

1. Verify v6.6.3 on production.
2. Use the pre-ride email utility with a real Bolt email.
3. Test the copied EDXEIX autofill script on the EDXEIX page.
4. Verify that the helper fills visible fields but does not save/submit.
5. Improve label matching based on screenshots/live behavior if any EDXEIX fields are missed.
6. Continue normalized mail intake only after the organization can function manually.
7. Do not enable live EDXEIX submission unless Andreas explicitly asks.
