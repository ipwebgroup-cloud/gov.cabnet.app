# gov.cabnet.app patch — V3 live-submit readiness gate

## What changed

Adds a V3-only live-submit readiness layer. This moves rows from `submit_dry_run_ready` to `live_submit_ready` after strict validation, including the verified starting-point guard.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness_cron_worker.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php
docs/PRE_RIDE_EMAIL_TOOL_V3_LIVE_SUBMIT_READINESS.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php

gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness_cron_worker.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness_cron_worker.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php --limit=20
```

## Commit mode

Only V3 status/events are written:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php --limit=20 --commit
```

## Suggested cron

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_live_submit_readiness_cron.log 2>&1
```

## Safety

This patch does not submit to EDXEIX. It does not call AADE and does not touch production submission tables.
