# Patch: V3 Fast Queue Intake Gate

## What changed

This patch lowers the isolated V3 queue-intake future gate from 20 minutes to 1 minute so real Bolt pre-ride emails that arrive only a few minutes before pickup can be queued automatically.

It keeps EDXEIX live submission disabled.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
docs/PRE_RIDE_EMAIL_TOOL_V3_FAST_INTAKE_GATE.md
PATCH_README.md
```

## Production file not included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php

gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php

gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
```

Run cron worker once and write to the same log path cron uses:

```bash
mkdir -p /home/cabnet/gov.cabnet.app_app/logs
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log 2>&1
tail -n 80 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log
```

Check V3 queue rows:

```bash
mysql cabnet_gov -e "SELECT id, dedupe_key, queue_status, customer_name, pickup_datetime, driver_name, vehicle_plate, created_at FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 10;"
```

## Expected result

A real Bolt pre-ride email that arrives at least 1 minute before pickup can be inserted into the V3-only queue automatically by cron.

Past rides remain blocked.

## Safety

- No production pre-ride tool changes.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- No EDXEIX server-side call.
- No AADE call.
- No email delete/move/mark-read.
