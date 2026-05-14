# V3 Live Adapter Kill-Switch Check

Version: `v3.0.60-v3-live-adapter-kill-switch-check`

This patch adds a read-only switchboard for the future V3 live adapter path.

It verifies all required pre-live conditions without making any external call or changing runtime state:

- master live-submit config is loaded
- `enabled=true`
- `mode=live`
- `adapter=edxeix_live`
- `hard_enable_live_submit=true`
- acknowledgement phrase is present
- selected queue row is currently `live_submit_ready`
- pickup is still future-safe
- required payload fields are present
- starting point is operator-verified
- operator approval is valid and unexpired
- selected adapter exists and is live-capable

Current expected state remains blocked because live submit is disabled.

## Safety boundary

The checker does not:

- call Bolt
- call EDXEIX
- call AADE
- write to the database
- change queue status
- write production submission tables
- touch V0
- change cron schedules
- change SQL schema

## CLI

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php"
```

Optional row-specific check:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --queue-id=418"
```

JSON:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --json"
```

## Ops page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php
```

Optional row-specific URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php?queue_id=418
```

## Current expected result

The checker should return `OK: no` and show master-gate blocks such as:

- `master_gate: enabled is false`
- `master_gate: mode is not live`
- `master_gate: adapter is not edxeix_live`
- `master_gate: hard_enable_live_submit is false`

That is the correct safe state until Andreas explicitly approves live-submit gate opening.
