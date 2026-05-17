# gov.cabnet.app — Bolt → EDXEIX Integration

Plain PHP + mysqli/MariaDB project for a safe Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue/readiness/diagnostic workflow.

## Current safety posture

No unattended EDXEIX submission is enabled by this repository package.

Current work is limited to:

- Bolt reference sync
- Bolt order sync
- normalized local bookings
- mapping checks
- EDXEIX payload preview/preflight
- local queue visibility
- readiness audit
- dry-run/local audit behavior by default
- EDXEIX submit diagnostics and redirect classification
- candidate discovery for real future Bolt trips

Live EDXEIX submission must remain disabled unless a real eligible future Bolt trip exists, preflight passes, diagnostic candidate discovery confirms eligibility, and Andreas explicitly requests a supervised one-shot live-submit diagnostic.

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
/ops/index.php
/ops/bolt-live.php
/ops/jobs.php
/ops/readiness.php
/ops/submit.php
/ops/edxeix-submit-diagnostic.php
```

## Current EDXEIX diagnostic posture

- Queue 2398 supervised automatic POST test is closed and unconfirmed.
- HTTP 302 alone is not proof of saved EDXEIX contract.
- v3.2.21 diagnostic adds candidate discovery and a +30 minute minimum diagnostic future guard.
- Diagnostic web mode is dry-run/read-only and never POSTs to EDXEIX.
- Diagnostic CLI transport remains blocked unless all live gates and diagnostic gates pass for an explicitly selected real future booking.

## Initial install notes

1. Copy `gov.cabnet.app_config/config.php.example` to `gov.cabnet.app_config/config.php` on the server.
2. Copy `gov.cabnet.app_config/bolt.php.example` to `gov.cabnet.app_config/bolt.php` on the server if using the Bolt override loader.
3. Fill real secrets only on the server. Never commit them.
4. Import the schema/migrations needed for the target environment.
5. Run `/bolt_readiness_audit.php`, `/ops/readiness.php`, and `/ops/edxeix-submit-diagnostic.php`.
6. Do not enable live submit behavior until a real future Bolt ride passes preflight and diagnostic discovery.
