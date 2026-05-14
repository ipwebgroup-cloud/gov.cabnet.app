# V3 Adapter Row Simulation

Version: `v3.0.67-v3-adapter-row-simulation`

## Purpose

Adds a V3-only read-only simulation layer for the future EDXEIX live adapter skeleton.

The simulation selects a real V3 queue row, builds the final EDXEIX field package, and calls the local `EdxeixLiveSubmitAdapterV3` skeleton. The skeleton must remain non-live-capable and must return `submitted=false`.

## Safety boundary

This patch does not:

- call Bolt
- call EDXEIX
- call AADE
- write database rows
- change queue status
- write production submission tables
- enable live submit
- change cron schedules
- change SQL schema
- touch V0

## Added files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php`

## What the simulation checks

- master gate config state
- selected V3 queue row
- payload completeness
- starting-point verification
- operator approval validity
- package artifact count
- EDXEIX field package mapping
- future adapter class presence
- adapter contract methods
- adapter `isLiveCapable()` value
- adapter `submit()` result
- confirmation that `submitted=false`

## Expected current result

The current expected result is:

```text
Simulation safe: yes
OK: yes
submitted=false
live_capable=no
```

The page may still show final blocks such as:

```text
master_gate: enabled is false
master_gate: mode is not live
master_gate: adapter is not edxeix_live
master_gate: hard_enable_live_submit is false
queue: row is not live_submit_ready
queue: pickup is not future-safe
approval: no valid closed-gate rehearsal approval found
```

Those blocks are expected and safe. They confirm that live submit remains closed.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php --json"
```

Optional row-specific verification:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php --queue-id=427"
```

Ops page:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php
```
