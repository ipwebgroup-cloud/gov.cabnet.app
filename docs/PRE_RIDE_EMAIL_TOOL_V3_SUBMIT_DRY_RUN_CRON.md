# V3 Submit Dry-Run Cron Worker

Adds a private cron worker for the isolated V3 Bolt pre-ride email queue.

## File

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
```

## Purpose

This worker runs the existing V3 submit dry-run worker automatically. It moves rows from `queued` to `submit_dry_run_ready` when strict V3 submit preflight checks pass.

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- No production `/ops/pre-ride-email-tool.php` changes.
- Commit mode writes only to:
  - `pre_ride_email_v3_queue`
  - `pre_ride_email_v3_queue_events`

## Manual verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php --dry-run
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
```

## Suggested cron

Use a separate log from the intake cron:

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_submit_dry_run_cron.log 2>&1
```

## Expected flow

```text
V3 intake cron queues eligible email
→ V3 submit dry-run cron validates queued row
→ row becomes submit_dry_run_ready
→ later controlled submit stage can use it
```
