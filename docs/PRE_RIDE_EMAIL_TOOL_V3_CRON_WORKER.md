# V3 Pre-Ride Email Cron Worker

This patch adds the missing V3 cron worker:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
```

## Purpose

The worker runs the existing V3 intake script automatically from cron.

It queues only future-ready, parser-complete, mapped Bolt pre-ride emails into the V3-only tables:

```text
pre_ride_email_v3_queue
pre_ride_email_v3_queue_events
```

## Safety

The worker does not call EDXEIX, does not call AADE, does not write to `submission_jobs`, does not write to `submission_attempts`, and does not mark/delete/move email.

## Cron command

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log 2>&1
```

## Manual test

```bash
mkdir -p /home/cabnet/gov.cabnet.app_app/logs
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
tail -n 80 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log
```

## Dry-run manual test

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php --dry-run
```
