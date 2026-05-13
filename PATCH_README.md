# gov.cabnet.app — V3 Queue Expiry Guard Patch

## What changed

Adds a V3-only expiry guard so stale active V3 rows do not stay actionable after pickup time passes.

This addresses the issue seen after the submit dry-run alias fix: valid rows can pass readiness with only a few minutes remaining, but if they are not completed quickly, they must be blocked automatically instead of staying queued/ready.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php
gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard_cron_worker.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-expiry-guard.php
docs/PRE_RIDE_EMAIL_TOOL_V3_EXPIRY_GUARD.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php

gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard_cron_worker.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-expiry-guard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-expiry-guard.php
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard_cron_worker.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-expiry-guard.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php --limit=500
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php --limit=500 --commit
```

## Suggested cron

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_expiry_guard_cron.log 2>&1
```

## Dashboard

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-expiry-guard.php
```

## Safety

- Production pre-ride-email-tool.php is untouched.
- No EDXEIX calls.
- No AADE calls.
- No production submission tables.
- Commit mode writes only V3 queue/status/events.

## Git commit title

```text
Add V3 queue expiry guard
```

## Git commit description

```text
Adds a V3-only expiry guard that blocks active V3 queue rows once their pickup time is no longer future-safe. This prevents queued, submit_dry_run_ready, or live_submit_ready rows from remaining actionable after pickup time passes.

The patch includes a CLI guard, app-owned-lock cron worker, and read-only ops dashboard.

Safety boundaries:
- Production pre-ride-email-tool.php is untouched.
- No EDXEIX calls.
- No AADE calls.
- No production submission_jobs writes.
- No production submission_attempts writes.
- Commit mode writes only to pre_ride_email_v3_queue and pre_ride_email_v3_queue_events.
```
