# Patch README — v3.0.74 V3 Live Gate Drift Guard

## What changed

Adds a read-only V3 live gate drift guard.

The guard verifies that the V3 live-submit master gate remains in the expected disabled pre-live posture and detects accidental live-gate drift before any future real adapter phase.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php
docs/V3_AUTOMATION_PRE_LIVE_STATUS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php

docs/V3_AUTOMATION_PRE_LIVE_STATUS.md
→ repo docs/V3_AUTOMATION_PRE_LIVE_STATUS.md

HANDOFF.md
→ repo root HANDOFF.md

CONTINUE_PROMPT.md
→ repo root CONTINUE_PROMPT.md

PATCH_README.md
→ repo root PATCH_README.md
```

## SQL

No SQL changes.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php --json"
```

Ops URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php
```

## Expected result

While live submit remains intentionally disabled:

```text
OK: yes
Expected disabled pre-live posture: yes
Live risk detected: no
Full live switch looks open: no
```

## Safety

```text
No Bolt call
No EDXEIX call
No AADE call
No DB writes
No queue status changes
No production submission tables
V0 untouched
```

## Git commit title

```text
Add V3 live gate drift guard
```

## Git commit description

```text
Adds a read-only V3 live gate drift guard CLI and Ops page.

The guard checks the server-only live submit gate config, detects accidental deviations from the expected disabled pre-live posture, scans adapter files for live/network-capable signals, and displays latest proof bundle/package artifact presence.

No live submission is enabled. No Bolt call, no EDXEIX call, no AADE call, no DB writes, no queue status changes, no production submission table writes, and V0 remains untouched.
```
