# gov.cabnet.app — V3 Cron App-Owned Locks Patch

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
docs/PRE_RIDE_EMAIL_TOOL_V3_APP_LOCKS.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php

gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
```

## What changed

The V3 cron workers now use app-owned lock files under:

```text
/home/cabnet/gov.cabnet.app_app/storage/locks/
```

instead of `/tmp`.

## After upload

Run:

```bash
mkdir -p /home/cabnet/gov.cabnet.app_app/storage/locks
chown -R cabnet:cabnet /home/cabnet/gov.cabnet.app_app/storage
chmod 755 /home/cabnet/gov.cabnet.app_app/storage
chmod 755 /home/cabnet/gov.cabnet.app_app/storage/locks
rm -f /tmp/gov_cabnet_pre_ride_email_v3_cron_worker.lock
rm -f /tmp/gov_cabnet_pre_ride_email_v3_submit_dry_run_cron_worker.lock
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php
su -s /bin/bash -c '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log 2>&1' cabnet
su -s /bin/bash -c '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_submit_dry_run_cron.log 2>&1' cabnet
tail -n 40 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log
tail -n 40 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_submit_dry_run_cron.log
```

Expected: no `/tmp` lock permission errors and both workers finish with `exit_code=0`.

## SQL

None.

## Production safety

This patch does not include or modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```
