# Patch: v3.0.54-v3-closed-gate-adapter-diagnostics

## What changed

Adds V3-only closed-gate live adapter diagnostics.

New files:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php
docs/V3_CLOSED_GATE_ADAPTER_DIAGNOSTICS.md
docs/V3_AUTOMATION_NEXT_STEPS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php
```

Docs should be kept in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php
```

## Expected result

The diagnostics should load and show:

```text
V3 only
No EDXEIX call
No AADE call
No DB writes
V0 untouched
Live submit blocked by master gate
```

## Git commit title

```text
Add V3 closed-gate adapter diagnostics
```

## Git commit description

```text
Adds V3-only closed-gate diagnostics for the future live-submit adapter path.

The new CLI and Ops page inspect master gate state, selected adapter wiring, selected queue row readiness, required payload fields, verified starting point, operator approval state, package export availability, and final live-submit block reasons.

This prepares the V3 automation path for future closed-gate live adapter skeleton work while keeping live submit disabled.

No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, queue status changes, production submission tables, cron schedules, or SQL schema are changed.
```
