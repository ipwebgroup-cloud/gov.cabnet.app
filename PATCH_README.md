# gov.cabnet.app — V3 submit starting-point guard patch

## What changed

The V3 submit preflight and V3 submit dry-run worker now enforce the verified V3 starting-point options table before a queue row can be marked submit-dry-run-ready.

This prevents rows with a starting point ID that is not available in the EDXEIX company/lessor form from proceeding through the V3 submit readiness stage.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
docs/PRE_RIDE_EMAIL_TOOL_V3_SUBMIT_STARTING_POINT_GUARD.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php

gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
```

## SQL

None. This patch uses the already-installed table:

```text
pre_ride_email_v3_starting_point_options
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php --status=all --limit=20
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php --status=queued --limit=20
```

## Expected result

A row with:

```text
lessor_id = 2307
starting_point_id = 1455969
```

can pass the starting-point guard.

A row with:

```text
lessor_id = 2307
starting_point_id = 6467495
```

is blocked from submit dry-run readiness.

## Safety

- Production `/ops/pre-ride-email-tool.php` is untouched.
- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Dry-run worker commit mode writes only to V3 queue/status/events.
