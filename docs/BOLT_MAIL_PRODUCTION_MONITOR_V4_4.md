# Bolt Mail Production Monitor v4.4

Date: 2026-05-07
Project: gov.cabnet.app Bolt → EDXEIX bridge

## Purpose

v4.4 improves production monitoring for the safe Bolt mail dry-run workflow. It does not enable live EDXEIX submission.

The patch adds clearer links between:

- `bolt_mail_intake`
- `normalized_bookings` rows created from Bolt mail
- `bolt_mail_dry_run_evidence`
- raw preflight JSON output

## Safety posture

Expected configuration remains:

```text
app.dry_run=true
edxeix.live_submit_enabled=false
edxeix.future_start_guard_minutes=2
app.timezone=Europe/Athens
```

This patch:

- does not create `submission_jobs`
- does not create `submission_attempts`
- does not POST to EDXEIX
- does not enable live submit
- keeps the workflow limited to local mail intake, local normalized bookings, and local dry-run evidence

## Changed behavior

### Mail Status dashboard

`/ops/mail-status.php` now shows:

- total dry-run evidence rows
- recent dry-run evidence rows
- direct evidence detail links
- evidence preview links from recent `source='bolt_mail'` bookings
- updated production rule text for the v4.3/v4.4 automated dry-run state

### Dry-run Evidence dashboard

`/ops/mail-dry-run-evidence.php` now supports:

- evidence detail view by `evidence_id`
- stored request payload display
- stored mapping snapshot display
- stored safety snapshot display
- direct links from bookings to evidence preview/detail
- synthetic-only cleanup preview and execution

Synthetic cleanup requires typing:

```text
DELETE_SYNTHETIC_ONLY
```

The cleanup is intentionally narrow. It only targets synthetic `CABNET TEST` / synthetic-marker rows, unlinks synthetic intake rows, deletes synthetic dry-run evidence, and deletes synthetic `source='bolt_mail'` bookings. It does not touch jobs, attempts, live configuration, or real Bolt rows.

### Auto Dry-run page

`/ops/mail-auto-dry-run.php` now links created booking IDs and evidence IDs directly into the dry-run evidence monitor.

### Raw Preflight JSON

`/bolt_edxeix_preflight.php` now:

- reports `guard_minutes` from `edxeix.future_start_guard_minutes`
- defaults the fallback guard to 2 minutes when config is missing
- adds `source_flow`, `is_bolt_mail`, and linked `mail_intake` context when available
- replaces the old hardcoded blocker label `started_at_not_30_min_future` with `started_at_not_future_guard_safe`

### Shared config fallback

`gov.cabnet.app_app/lib/bolt_sync_lib.php` now uses a 2-minute default fallback for `edxeix.future_start_guard_minutes` so old/raw preflight displays no longer imply a stale 30-minute default.

## Verification

Run syntax checks after upload:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php
php -l /home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-auto-dry-run.php
php -l /home/cabnet/public_html/gov.cabnet.app/bolt_edxeix_preflight.php
```

Check config posture:

```bash
php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo "app_timezone=".($c["app"]["timezone"] ?? "MISSING").PHP_EOL; echo "dry_run=".(!empty($c["app"]["dry_run"]) ? "true" : "false").PHP_EOL; echo "future_start_guard_minutes=".($c["edxeix"]["future_start_guard_minutes"] ?? "MISSING").PHP_EOL; echo "live_submit_enabled=".(!empty($c["edxeix"]["live_submit_enabled"]) ? "true" : "false").PHP_EOL;'
```

Expected:

```text
app_timezone=Europe/Athens
dry_run=true
future_start_guard_minutes=2
live_submit_enabled=false
```

Check safety counts:

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" -e "SELECT COUNT(*) AS bolt_mail_bookings FROM normalized_bookings WHERE source='bolt_mail';"
mysql "$DB_NAME" -e "SELECT COUNT(*) AS dry_run_evidence FROM bolt_mail_dry_run_evidence;"
mysql "$DB_NAME" -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs;"
mysql "$DB_NAME" -e "SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
```

Open dashboards:

```text
https://gov.cabnet.app/ops/mail-status.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/mail-auto-dry-run.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/mail-dry-run-evidence.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
```

## Next safe step

Monitor the next real future Bolt Ride details email and confirm:

1. Import cron imports it within 1 minute.
2. Auto dry-run worker creates a local `source='bolt_mail'` booking and dry-run evidence only.
3. `submission_jobs` remains unchanged.
4. `submission_attempts` remains unchanged.
5. Live EDXEIX submit remains disabled.
