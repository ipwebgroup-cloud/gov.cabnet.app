# Patch: v3.0.61-v3-kill-switch-table-exists-fix

## What changed

Fixes the V3 live adapter kill-switch checker table-existence query.

The previous checker used:

```sql
SHOW TABLES LIKE ?
```

The live server returned a MariaDB syntax error near `?`. The checker now uses `INFORMATION_SCHEMA.TABLES` with a prepared parameter.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
docs/V3_KILL_SWITCH_TABLE_EXISTS_FIX.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
```

Docs go into the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --json"
```

## Expected result

The checker should run and return `OK: no` because the live-submit gate is still intentionally closed. It should not show a SQL syntax error.

## Safety

No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, queue status changes, production submission tables, cron schedules, or SQL schema are changed.
