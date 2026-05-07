# gov.cabnet.app — Bolt → EDXEIX Bridge HANDOFF

## Current phase

v4.9 Final Dry-run Production Freeze.

The project is running in production-safe dry-run mode:

- Mail intake cron is active.
- Auto dry-run evidence cron is active.
- Bolt driver directory sync cron is active.
- Driver copy emails are active and resolved by driver identity, not plate.
- Driver copy formatting is adjusted: end time = pickup + 30 minutes; price range shows first value only.
- Launch readiness shows hardening-ready dry-run posture.
- Credential rotation remains the manual gate before any live-submit phase.
- Live EDXEIX submission remains OFF.

## v4.9 additions

New read-only page:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/production-freeze.php
```

New CLI marker script:

```text
/home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php
```

The CLI writes a no-secret marker only when dry-run production posture is safe:

```text
/home/cabnet/gov.cabnet.app_app/storage/security/production_dry_run_freeze.json
```

## Safety posture

- `app.dry_run = true`
- `edxeix.live_submit_enabled = false`
- `edxeix.future_start_guard_minutes = 2`
- `submission_jobs = 0` expected
- `submission_attempts = 0` expected
- No live EDXEIX POST in v4.x

## Credential rotation

Before any v5.0 live-submit design, rotate:

- ops/internal API key
- Bolt credentials/tokens if exposed
- EDXEIX credentials/session material
- mailbox/forwarding credentials if exposed

After rotation, run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php --ops-key --bolt --edxeix --mailbox --by=Andreas
```

## v4.9 freeze command

After reviewing production-freeze.php, run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php --by=Andreas
```

## Next phase

After credential rotation and v4.9 freeze are both acknowledged, prepare a separate v5.0 live-submit design only if Andreas explicitly approves. Do not enable live submit automatically.
