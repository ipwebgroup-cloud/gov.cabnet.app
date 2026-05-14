# V3 Pre-Live Switchboard

Version: `v3.0.63-v3-pre-live-switchboard`

## Purpose

Adds a read-only V3 pre-live switchboard that consolidates the current state of the closed-gate automation path before any future real EDXEIX adapter work.

The switchboard reports:

- master live-submit gate state
- selected V3 queue row
- payload completeness
- verified starting point status
- operator approval status
- adapter selection / live capability
- local package export artifact state
- final block reasons

## Safety

This patch does not:

- call Bolt
- call EDXEIX
- call AADE
- write DB rows
- change queue status
- write production submission tables
- touch V0
- enable live submit
- change SQL schema
- change cron schedules

## Runtime files

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
```

## Expected current result

The current expected result remains:

```text
OK: no
```

because live submit is intentionally blocked by master gate and adapter controls.

The good expected state is that the blocks are explicit, such as:

```text
master_gate: enabled is false
master_gate: mode is not live
master_gate: adapter is not edxeix_live
master_gate: hard_enable_live_submit is false
adapter: selected adapter is not edxeix_live
```

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --json"
```
