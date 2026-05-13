# gov.cabnet.app — V3 Submit Dry-Run Cron Worker Patch

## What changed

Adds a private V3-only cron worker:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
```

It automatically runs the existing V3 submit dry-run worker and can mark queued V3 rows as `submit_dry_run_ready` when strict preflight checks pass.

## Production file not touched

This patch does not include or modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Upload path

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
mkdir -p /home/cabnet/gov.cabnet.app_app/logs
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php --dry-run
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_submit_dry_run_cron.log 2>&1
tail -n 80 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_submit_dry_run_cron.log
```

## Suggested cron

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_submit_dry_run_cron.log 2>&1
```

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production submission_jobs writes.
- No production submission_attempts writes.
- V3-only queue/status/events writes through the child dry-run worker.
