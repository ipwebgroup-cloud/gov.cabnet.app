# gov.cabnet.app — V3 Fast Pipeline Exit-Code Fix

## What changed

Fixes the V3 fast pipeline runner so successful/no-op child scripts are not incorrectly reported as `FAIL` because of PHP `proc_close()` returning `-1` after `proc_get_status()`.

## Files included

- `gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php`
- `docs/PRE_RIDE_EMAIL_TOOL_V3_FAST_PIPELINE_EXITCODE_FIX.md`

## Upload paths

- `gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php` → `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php` → `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php`

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php --limit=50
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php --limit=50 --commit
```

## Expected result

With no future-safe rows, no-op stages should report `OK`, not `FAIL`. Intake may still show zero inserted if all current Maildir candidates are already past/blocked.

## Safety

- No EDXEIX call.
- No AADE call.
- No production submission table writes.
- Production `pre-ride-email-tool.php` untouched.
