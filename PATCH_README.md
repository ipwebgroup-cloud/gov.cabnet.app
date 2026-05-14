# PATCH README — v3.0.62 V3 Kill-Switch Approval Alignment

## What changed

Adds a V3-only maintenance script:

```text
gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php
```

The script patches:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
```

to align approval validation with the final rehearsal approval logic.

## Files included

```text
gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php
docs/V3_KILL_SWITCH_APPROVAL_ALIGNMENT.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php
→ /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php
```

Docs go in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php --check"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php --apply"

php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --queue-id=427"
```

## Expected result

Approval should report valid for a still-valid approved row.

The overall kill-switch should still report:

```text
OK: no
```

because the live-submit master gate remains closed.

## Commit title

```text
Align V3 kill-switch approval validation
```

## Commit description

```text
Adds a V3-only maintenance script to align the live adapter kill-switch checker approval validation with the final rehearsal approval logic.

The final rehearsal accepted row 427's closed-gate rehearsal approval, but the kill-switch checker still reported no valid approval. The patch updates the checker approval logic to use queue_id or dedupe_key, SQL-side expiry validation, approved/valid/active statuses, closed_gate_rehearsal_only scope, and revoked checks.

No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, queue status changes, production submission table writes, cron schedules, or SQL schema are changed.
```
