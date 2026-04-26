# gov.cabnet.app Patch — Bolt Evidence Bundle v1.3

## What changed

Adds a read-only Evidence Bundle page for faster real future Bolt ride testing.

The page summarizes existing sanitized Bolt visibility snapshots and readiness state so the operator can review one session report instead of collecting scattered screenshots.

## Files included

```text
public_html/gov.cabnet.app/ops/evidence-bundle.php
docs/BOLT_EVIDENCE_BUNDLE.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Exact upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/evidence-bundle.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/evidence-bundle.php
```

Optional repo/documentation files:

```text
docs/BOLT_EVIDENCE_BUNDLE.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/evidence-bundle.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/evidence-bundle.php
https://gov.cabnet.app/ops/evidence-bundle.php?format=json
https://gov.cabnet.app/ops/dev-accelerator.php
https://gov.cabnet.app/ops/bolt-api-visibility.php
https://gov.cabnet.app/ops/readiness.php
```

## Expected result

- Evidence Bundle page loads.
- JSON endpoint returns valid JSON.
- Page shows readiness status and sanitized snapshot timeline.
- If no snapshots exist for today, it says it is waiting for evidence.
- It does not call Bolt.
- It does not call EDXEIX.
- It does not create jobs.
- It does not enable live submission.

## Git commit title

```text
Add Bolt test evidence bundle
```

## Git commit description

```text
Adds a read-only Bolt test session evidence page for faster diagnostics during real future ride testing. The page consolidates readiness state, sanitized Bolt visibility snapshots, stage coverage, watch match status, and copy/paste recap output while keeping live EDXEIX submission disabled.
```
