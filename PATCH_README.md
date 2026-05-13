# gov.cabnet.app — V3 Starting-Point Guard Patch

## What changed

Adds V3-only verified EDXEIX starting-point options and a guard worker that blocks active V3 queue rows when their `starting_point_id` is known-invalid for the selected lessor.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-starting-point-guard.php
gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard.php
gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard_cron_worker.php
gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_starting_point_options.sql
docs/PRE_RIDE_EMAIL_TOOL_V3_STARTING_POINT_GUARD.md
PATCH_README.md
```

## Production file not touched

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-starting-point-guard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-starting-point-guard.php

gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard.php

gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard_cron_worker.php

gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_starting_point_options.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_starting_point_options.sql
```

## SQL

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_starting_point_options.sql
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-starting-point-guard.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard_cron_worker.php
```

## Dry-run

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard.php --limit=50
```

## Commit guard action

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard.php --limit=50 --commit
```

## Suggested cron

```cron
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_starting_point_guard_cron.log 2>&1
```

## Page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-starting-point-guard.php
```

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production route changes.
- No production queue writes.
- Guard commit writes only to V3 queue/status/events.
