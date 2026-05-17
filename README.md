# gov.cabnet.app — Bolt → EDXEIX Integration

Plain PHP + mysqli/MariaDB project for a safe Bolt Fleet API / pre-ride email → normalized local readiness → EDXEIX preflight/queue workflow.

## Current safety posture

No unattended live EDXEIX submission is enabled by this repository package. Current work is limited to:

- Bolt reference sync
- Bolt order sync
- normalized local bookings
- mapping checks
- EDXEIX payload preview/preflight
- local queue visibility
- readiness audit
- dry-run/local audit behavior
- EDXEIX submit redirect diagnostics
- dry-run pre-ride email future candidate diagnostics

Live EDXEIX submission must remain disabled unless a real eligible future candidate exists and the owner explicitly requests a supervised live-submit update.

## cPanel layout

Expected server paths:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

Repository layout mirrors that cPanel structure:

```text
public_html/gov.cabnet.app/      Public endpoints and operations UI
gov.cabnet.app_app/              Private app library, source, CLI, storage placeholders
gov.cabnet.app_config/           Config examples only; real config ignored
gov.cabnet.app_sql/              Schema/migration SQL, sanitized where needed
docs/                            Scope, deployment, security, diagnostics, and recommendations
```

## Key public endpoints

```text
/bolt_sync_reference.php
/bolt_sync_orders.php
/bolt_edxeix_preflight.php
/bolt_jobs_queue.php
/bolt_stage_edxeix_jobs.php
/bolt_readiness_audit.php
/bolt_submission_worker.php
/ops/index.php
/ops/bolt-live.php
/ops/jobs.php
/ops/readiness.php
/ops/submit.php
/ops/edxeix-submit-diagnostic.php
/ops/pre-ride-edxeix-candidate.php
/ops/pre-ride-email-tool.php
```

## Current validated status

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Readiness audit works and reports read-only behavior.
- Real historical Bolt orders are blocked from EDXEIX submission because they are terminal/cancelled and not at least +30 minutes in the future.
- v3.2.21 diagnostics confirmed no safe normalized booking candidate in the latest 75 rows.
- `future_start_guard_minutes` is 30 in live config.
- EDXEIX session diagnostic shows session file, cookie, and CSRF present, but no live submit is enabled.
- v3.2.22 adds dry-run pre-ride email future candidate diagnostics without changing production V0.

## Initial install notes

1. Copy `gov.cabnet.app_config/config.php.example` to `gov.cabnet.app_config/config.php` on the server.
2. Copy `gov.cabnet.app_config/bolt.php.example` to `gov.cabnet.app_config/bolt.php` on the server if using the Bolt override loader.
3. Fill real secrets only on the server. Never commit them.
4. Import the schema/migrations needed for the target environment.
5. Run `/bolt_readiness_audit.php` and `/ops/readiness.php`.
6. Do not enable live submit behavior until a real future candidate passes preflight.

## v3.2.22 pre-ride candidate commands

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75 --pre-ride-latest=1
```

Optional metadata capture after additive SQL migration:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --write=1
```
