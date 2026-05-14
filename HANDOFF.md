# HANDOFF — gov.cabnet.app V3 Automation

## Latest patch

v3.0.62-v3-kill-switch-approval-alignment

## Current verified state before this patch

- V3 readiness pipeline proven.
- V3 approval workflow proven.
- Row 427 reached live_submit_ready.
- Row 427 approval inserted with required phrase.
- Payload package export succeeded.
- Final rehearsal for row 427 blocked only by master gate controls.
- Kill-switch checker was operational after v3.0.61, but its approval query reported no valid approval even though final rehearsal accepted the approval.

## Patch purpose

Add a V3-only fixer script to align the kill-switch checker approval validation with final rehearsal approval validation.

## Safety

- No V0 changes.
- No live submit enabling.
- No EDXEIX calls.
- No AADE calls.
- No DB writes from the fixer.
- No queue mutation.
- No SQL schema changes.
- No cron changes.

## Next verification

Run:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php --check"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php --apply"
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --queue-id=427"
```
