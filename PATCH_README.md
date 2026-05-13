# gov.cabnet.app patch — V3 start options alias fix

## Purpose

Fix the V3 submit dry-run/preflight error:

```text
Unknown column 'lessor_id' in 'SELECT'
```

The verified starting-point options table uses `edxeix_lessor_id` and `edxeix_starting_point_id`, while the V3 dry-run/preflight workers were querying `lessor_id` and `starting_point_id` directly.

## Files included

```text
gov.cabnet.app_app/cli/fix_v3_start_options_aliases.php
docs/PRE_RIDE_EMAIL_TOOL_V3_START_OPTIONS_ALIAS_FIX.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/cli/fix_v3_start_options_aliases.php
→ /home/cabnet/gov.cabnet.app_app/cli/fix_v3_start_options_aliases.php
```

## Run

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/fix_v3_start_options_aliases.php
php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_start_options_aliases.php --dry-run
php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_start_options_aliases.php --apply

php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php --status=queued --limit=20
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php --status=queued --limit=20
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php --status=queued --limit=20 --commit
```

## Expected

The active queued row should pass the starting-point guard:

```text
Starting-point guard: verified
```

If the pickup is still future-safe and all required data is present, commit mode marks it:

```text
queue_status = submit_dry_run_ready
```

## Safety

- No EDXEIX call.
- No AADE call.
- No production submission table writes.
- Production pre-ride-email-tool.php untouched.
