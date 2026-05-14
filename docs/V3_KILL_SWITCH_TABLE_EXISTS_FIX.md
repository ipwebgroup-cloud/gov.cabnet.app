# V3 Kill-Switch Table Exists Fix

Version: v3.0.61-v3-kill-switch-table-exists-fix

## Purpose

Fixes the V3 live adapter kill-switch checker after the initial v3.0.60 run failed with a MariaDB syntax error near `?`.

The issue came from using a prepared placeholder in:

```sql
SHOW TABLES LIKE ?
```

On the live cPanel/MariaDB environment this did not execute reliably. The checker now uses `INFORMATION_SCHEMA.TABLES` with a prepared parameter:

```sql
SELECT COUNT(*) AS c
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = ?
LIMIT 1
```

## Scope

Changed file:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
```

## Safety

This remains read-only and V3-only:

- No Bolt call
- No EDXEIX call
- No AADE call
- No DB writes
- No queue status changes
- No production submission tables
- No V0 changes
- No live-submit enabling
- No SQL schema changes
- No cron changes

## Expected verification result

The checker should now load the config, select a V3 queue row, inspect approval/gate/adapter state, and return `OK: no` because the master gate is intentionally closed.

Expected blocks include:

```text
master_gate: enabled is false
master_gate: mode is not live
master_gate: adapter is not edxeix_live
master_gate: hard_enable_live_submit is false
```
