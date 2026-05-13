# gov.cabnet.app Patch — V3 Fast Pipeline Runner

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php
gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php
docs/PRE_RIDE_EMAIL_TOOL_V3_FAST_PIPELINE.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php

gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php --limit=50
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php --limit=50 --commit
```

## Cron

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline.log 2>&1
```

## Safety

- Production `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php` is untouched.
- No EDXEIX call.
- No AADE call.
- No production submission table writes.
- Existing V3 workers remain responsible for V3-only writes.
