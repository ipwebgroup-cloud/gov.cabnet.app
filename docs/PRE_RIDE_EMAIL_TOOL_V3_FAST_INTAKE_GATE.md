# gov.cabnet.app — V3 Fast Queue Intake Gate

## Version

v3.0.9 — Fast queue intake gate for real Bolt timing.

## Purpose

The previous V3 queue gate required the pickup to be at least 20 minutes in the future. That proved the safety behavior, but real Bolt pre-ride emails can arrive only a few minutes before pickup.

This patch lowers the V3 queue-intake gate to 1 minute so the automated V3 queue can catch real operational emails quickly.

## Safety boundaries

This patch still does not enable live EDXEIX submission.

V3 still does not:

- Modify `/ops/pre-ride-email-tool.php`.
- Write to production `submission_jobs`.
- Write to production `submission_attempts`.
- Call EDXEIX server-side.
- Call AADE.
- Delete, move, or mark email as read.

The cron worker writes only to:

- `pre_ride_email_v3_queue`
- `pre_ride_email_v3_queue_events`

## New threshold

Default V3 queue gate:

```text
1 minute in the future
```

A ride with pickup already in the past is still blocked.

## CLI override

The CLI supports:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php --limit=20 --min-future-minutes=1
```

Environment override:

```bash
GOV_CABNET_V3_MIN_FUTURE_MINUTES=1
```

## Cron worker

The cron worker now defaults to 1 minute and passes the threshold to the intake script.

Existing cron line may remain:

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log 2>&1
```

Optional explicit version:

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php --min-future-minutes=1 >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log 2>&1
```

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
```

Manual cron test with log output:

```bash
mkdir -p /home/cabnet/gov.cabnet.app_app/logs
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log 2>&1
tail -n 80 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log
```

Queue verification:

```bash
mysql cabnet_gov -e "SELECT id, dedupe_key, queue_status, customer_name, pickup_datetime, driver_name, vehicle_plate, created_at FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 10;"
```
