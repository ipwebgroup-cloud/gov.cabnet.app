# gov.cabnet.app Patch v4.3 — Bolt Mail Auto Dry-run Evidence

## What changed

Adds a guarded automation layer that can auto-create local `source='bolt_mail'` preflight bookings from valid active `future_candidate` mail rows and record dry-run evidence snapshots.

## Files included

```text
gov.cabnet.app_app/src/Mail/BoltMailAutoDryRunService.php
gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php
public_html/gov.cabnet.app/ops/mail-auto-dry-run.php
docs/BOLT_MAIL_AUTO_DRY_RUN.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailAutoDryRunService.php
/home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php
/home/cabnet/public_html/gov.cabnet.app/ops/mail-auto-dry-run.php
```

## SQL

No new SQL. Requires v4.2 table `bolt_mail_dry_run_evidence`.

## Safety

No Bolt API call. No EDXEIX call. No `submission_jobs`. No `submission_attempts`. No live submit.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailAutoDryRunService.php
php -l /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-auto-dry-run.php
```

Preview:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --preview-only --json
```

Run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --limit=50 --json
```
