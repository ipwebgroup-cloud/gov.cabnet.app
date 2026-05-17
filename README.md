# gov.cabnet.app — Bolt → EDXEIX Integration

Plain PHP + mysqli/MariaDB project for a safe Bolt Fleet API / pre-ride email → normalized local readiness → EDXEIX preflight/diagnostic workflow.

## Current safety posture

No unattended live EDXEIX submission is enabled by this package. Current work is limited to:

- Bolt reference sync
- Bolt order sync
- normalized local bookings
- mapping checks
- EDXEIX payload preview/preflight
- local queue visibility
- readiness audit
- pre-ride email future candidate diagnostics
- dry-run/local audit behavior only

Live EDXEIX submission must remain disabled unless a real eligible future trip/pre-ride candidate exists and Andreas explicitly requests a supervised live-submit diagnostic.

## cPanel layout

Expected server paths:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## v3.2.23 note

v3.2.23 improves the separate pre-ride candidate diagnostic path by adding a fallback label parser inside `edxeix_pre_ride_candidate_lib.php`. The existing production `BoltPreRideEmailParser.php` remains untouched so production V0/manual tooling behavior is not changed.

## Key endpoints

```text
/ops/edxeix-submit-diagnostic.php
/ops/pre-ride-edxeix-candidate.php
/ops/index.php
/ops/bolt-live.php
/ops/jobs.php
/ops/readiness.php
/ops/submit.php
```

## Safety rules

- No historical, terminal, cancelled, expired, invalid, or past Bolt order may be submitted.
- Receipt-only `bolt_mail` rows stay blocked.
- Pre-ride email candidates must pass +30 minute future guard, parser, mapping, exclusion, and duplicate checks.
- Real credentials, sessions, cookies, tokens, API keys, and raw private data must remain server-only.
