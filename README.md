# gov.cabnet.app — Bolt → EDXEIX Integration

Plain PHP + mysqli/MariaDB project for a safe Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue workflow.

## Current safety posture

No live EDXEIX submission is enabled by this repository package. Current work is limited to:

- Bolt reference sync
- Bolt order sync
- normalized local bookings
- mapping checks
- EDXEIX payload preview/preflight
- local queue visibility
- readiness audit
- dry-run/local audit behavior only

Live EDXEIX submission must remain disabled unless a real eligible future Bolt trip exists and the owner explicitly requests a live submit update.

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
```

## Current validated status

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Database schema fixes for dedupe/defaults/decimal/null time columns have been applied in the working server line.
- Readiness audit works and reports read-only behavior.
- Real historical Bolt orders are blocked from EDXEIX submission because they are terminal/cancelled and not at least +30 minutes in the future.
- Queue was clean at the latest validation point: zero local jobs and zero recent attempts.

## Initial install notes

1. Copy `gov.cabnet.app_config/config.php.example` to `gov.cabnet.app_config/config.php` on the server.
2. Copy `gov.cabnet.app_config/bolt.php.example` to `gov.cabnet.app_config/bolt.php` on the server if using the Bolt override loader.
3. Fill real secrets only on the server. Never commit them.
4. Import the schema/migrations needed for the target environment.
5. Run `/bolt_readiness_audit.php` and `/ops/readiness.php`.
6. Do not enable live submit behavior until a real future Bolt ride passes preflight.

## Suggested first Git commit

```text
Initial sanitized gov.cabnet.app Bolt EDXEIX bridge
```

Description:

```text
Adds the sanitized cPanel project structure for the gov.cabnet.app Bolt → EDXEIX bridge, including public endpoints, private PHP libraries, SQL schema/migrations, operations UI, readiness audit, dry-run worker, docs, config examples, and repository ignore rules. Real credentials, sessions, logs, data dumps, and temporary public utility scripts are excluded.
```
