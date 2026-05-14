# V3 Kill-Switch Approval Alignment — v3.0.62

## Purpose

Align the V3 live adapter kill-switch checker approval validation with the already-verified final rehearsal approval validation.

The approval workflow and final rehearsal proved that row 427 had a valid closed-gate rehearsal approval. The kill-switch checker still reported:

```text
Approval: valid=no reason=no valid approval found
```

This patch fixes only the checker logic.

## Safety

This is V3-only.

It does not:

- call Bolt
- call EDXEIX
- call AADE
- write database rows
- change queue status
- write production submission tables
- touch V0
- enable live submit
- change SQL schema
- change cron schedules

## Files

```text
gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php
```

## Upload path

```text
/home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php
```

## Commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php --check"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php --apply"

php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --queue-id=427"
```

## Expected result

For a still-valid approved row, the kill-switch checker should show:

```text
Approval: valid=yes
```

The final result must still be:

```text
OK: no
```

because the master live-submit gate remains closed.

Expected remaining blocks:

```text
master_gate: enabled is false
master_gate: mode is not live
master_gate: adapter is not edxeix_live
master_gate: hard_enable_live_submit is false
adapter: selected adapter is not edxeix_live
```
