# Bolt Mail Auto Dry-run Evidence v4.3

Adds a guarded automation layer for the Bolt pre-ride mail bridge.

## Purpose

When a real `future_candidate` email arrives, the manual approval window can pass quickly. This patch adds a private worker that can automatically:

1. Read active `future_candidate` rows from `bolt_mail_intake`.
2. Create a local `normalized_bookings` row using the existing Mail Preflight bridge.
3. Record a local dry-run evidence snapshot in `bolt_mail_dry_run_evidence`.

## Safety contract

This worker does **not**:

- call Bolt,
- call EDXEIX,
- create `submission_jobs`,
- create `submission_attempts`,
- submit live.

It refuses to run if `app.dry_run` is false or `edxeix.live_submit_enabled` is true.

## Files

- `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailAutoDryRunService.php`
- `/home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/mail-auto-dry-run.php`

## CLI

Preview only:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --preview-only --json
```

Process active candidates:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --limit=50 --json
```

## Optional cron

Run after the mail importer cron:

```cron
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --limit=50 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_auto_dry_run.log 2>&1
```

## Ops URL

```text
https://gov.cabnet.app/ops/mail-auto-dry-run.php?key=YOUR_INTERNAL_API_KEY
```
