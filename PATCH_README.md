# gov.cabnet.app V3 Operator Console Patch

## Version

`v3.0.76-v3-live-operator-console`

## What changed

Added a read-only V3 Live Operator Console for the Bolt pre-ride email automation workflow.

The console summarizes:

- gate posture;
- live-risk/drift posture;
- current V3 queue metrics;
- current active queue rows;
- selected row payload fields;
- approval validity;
- starting-point verification;
- local live package artifacts;
- local proof bundles;
- adapter file scan signals.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
docs/V3_OPERATOR_CONSOLE_SCOPE.md
docs/V3_AUTOMATION_README.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Exact upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Upload docs to the repository root for tracking:

```text
docs/V3_OPERATOR_CONSOLE_SCOPE.md
docs/V3_AUTOMATION_README.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php

curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Expected Ops protection check:

```text
HTTP/1.1 302 Found
Location: /ops/login.php?next=%2Fops%2Fpre-ride-email-v3-live-operator-console.php
```

After login, open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php?queue_id=716
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php?json=1&queue_id=716
```

## Expected result

The page loads and shows:

```text
EXPECTED CLOSED PRE-LIVE GATE
NO LIVE RISK DETECTED
NO EDXEIX CALL
NO DB WRITES
```

For queue `#716`, while it remains future-safe and approval-valid, it should show:

```text
payload complete
start verified
approval valid
closed-gate proof ready
```

Live submission remains blocked by the master gate.

## Git commit title

```text
Add V3 live operator console
```

## Git commit description

```text
Adds a read-only V3 Live Operator Console for the Bolt pre-ride email automation workflow.

The console displays gate posture, live-risk status, queue metrics, active V3 queue rows, approval validity, starting-point verification, payload completeness, package artifacts, proof bundles, and adapter file drift signals.

The page is read-only and performs no Bolt calls, no EDXEIX calls, no AADE calls, no DB writes, no queue mutations, no production submission writes, and no V0 changes.

Live submission remains intentionally blocked by the disabled master gate and the non-live-capable EDXEIX adapter skeleton.
```
