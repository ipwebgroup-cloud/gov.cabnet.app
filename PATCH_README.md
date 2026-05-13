# gov.cabnet.app — V3 Fast Pipeline Pulse Patch

## What changed

Adds a V3-only pulse runner for the existing fast pipeline. It runs the ordered V3 pipeline repeatedly inside one cron minute, reducing the delay between a Bolt email arriving and the V3 queue/readiness stages processing it.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse.php
gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php
docs/PRE_RIDE_EMAIL_TOOL_V3_FAST_PIPELINE_PULSE.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse.php

gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse.php --limit=50 --cycles=2 --sleep=2

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse.php --limit=50 --cycles=2 --sleep=2 --commit
```

## Suggested cron

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log 2>&1
```

## New page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php
```

## Safety

- Production pre-ride-email-tool.php is untouched.
- No EDXEIX calls.
- No AADE calls.
- No production submission_jobs writes.
- No production submission_attempts writes.
- Commit mode delegates only to the existing V3-only fast pipeline.
