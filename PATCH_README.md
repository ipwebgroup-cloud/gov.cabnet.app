# Patch README — v3.0.67 V3 Adapter Row Simulation

## What changed

Adds a V3-only read-only adapter row simulation CLI and Ops page.

The simulation builds a final EDXEIX field package from a real V3 queue row and calls the local future adapter skeleton. The adapter must remain non-live-capable and return `submitted=false`.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php
docs/V3_ADAPTER_ROW_SIMULATION.md
docs/V3_AUTOMATION_NEXT_STEPS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php
```

Docs remain in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php --json"
```

Expected:

```text
Simulation safe: yes
submitted=false
live_capable=no
No EDXEIX call
No AADE call
V0 untouched
```

## Commit title

```text
Add V3 adapter row simulation
```

## Commit description

```text
Adds a V3-only read-only adapter row simulation CLI and Ops page.

The simulation selects a real V3 queue row, builds the final EDXEIX field package, and calls the local future EDXEIX adapter skeleton. It confirms the adapter remains non-live-capable and returns submitted=false.

No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, DB writes, queue status changes, production submission table writes, cron schedules, or SQL schema are changed.
```
