# gov.cabnet.app — V3 cron worker patch

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
docs/PRE_RIDE_EMAIL_TOOL_V3_CRON_WORKER.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
```

## SQL

None.

## Verify

```bash
mkdir -p /home/cabnet/gov.cabnet.app_app/logs
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
tail -n 80 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log
```

## Cron line

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log 2>&1
```

## Safety

No EDXEIX call. No AADE call. No production `submission_jobs` write. No production `submission_attempts` write. The worker calls only the existing V3 intake script, which inserts only eligible future-ready records into the V3-only queue tables.
