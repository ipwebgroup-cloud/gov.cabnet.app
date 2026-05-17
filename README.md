# gov.cabnet.app — Bolt → EDXEIX Integration

Plain PHP + mysqli/MariaDB project for a safe Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue workflow.

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
- submit diagnostic tracing preparation

Live EDXEIX submission must remain disabled unless a real eligible future Bolt trip exists and the owner explicitly requests a one-shot live submit diagnostic/update.

HTTP 302 from EDXEIX must not be treated as saved/confirmed by itself. Queue 2398 returned HTTP 302, but no remote/reference ID was captured and no saved EDXEIX contract was confirmed.

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
docs/                            Scope, deployment, security, and recommendations
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
/edxeix-extension-payload.php
/ops/index.php
/ops/bolt-live.php
/ops/jobs.php
/ops/readiness.php
/ops/submit.php
/ops/edxeix-submit-diagnostic.php
```

## Key CLI tools

```text
/home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
/home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php
/home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php
```

## Current validated status

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Database schema fixes for dedupe/defaults/decimal/null time columns have been applied in the working server line.
- Readiness audit works and reports read-only behavior.
- Real historical Bolt orders are blocked from EDXEIX submission because they are terminal/cancelled and not sufficiently in the future.
- Queue 2398 one-shot test is closed as not confirmed / not saved.
- Next automation step is EDXEIX submit redirect-chain diagnostics, not unattended live submission.

## Initial install notes

1. Copy `gov.cabnet.app_config/config.php.example` to `gov.cabnet.app_config/config.php` on the server.
2. Copy `gov.cabnet.app_config/bolt.php.example` to `gov.cabnet.app_config/bolt.php` on the server if using the Bolt override loader.
3. Fill real secrets only on the server. Never commit them.
4. Import the schema/migrations needed for the target environment.
5. Run `/bolt_readiness_audit.php` and `/ops/readiness.php`.
6. Run `/ops/edxeix-submit-diagnostic.php` dry-run before any new supervised test.
7. Do not enable live submit behavior until a real future Bolt ride passes preflight and Andreas explicitly authorizes a one-shot diagnostic.

## Suggested commit for v3.2.20

```text
Add EDXEIX submit diagnostic tracing
```

Description:

```text
Adds dry-run EDXEIX submit diagnostics and a gated CLI redirect-chain trace tool so HTTP 302 can be classified before moving toward full automation. Updates scope, handoff, README, manifest, and documentation for the ASAP automation track while keeping unattended live submission disabled.
```
