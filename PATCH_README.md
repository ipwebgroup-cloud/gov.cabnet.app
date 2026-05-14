# Patch: v3.0.60-v3-live-adapter-kill-switch-check

## What changed

Adds a V3-only read-only live adapter kill-switch checker.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php
docs/V3_LIVE_ADAPTER_KILL_SWITCH_CHECK.md
docs/V3_AUTOMATION_NEXT_STEPS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php
```

Docs go to the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --json"
```

## Expected result

Current expected result is blocked / OK no, because live submit remains disabled.

## Safety

No Bolt call, no EDXEIX call, no AADE call, no DB writes, no queue status changes, no production submission tables, no V0 changes.
