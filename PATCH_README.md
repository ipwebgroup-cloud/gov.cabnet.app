# gov.cabnet.app Patch — Bolt Dev Accelerator v1.2

## What changed

Adds a new safe development/operator cockpit:

```text
/ops/dev-accelerator.php
```

The page speeds up the next real future Bolt test by consolidating readiness checks, fast timeline capture buttons, auto-watch links, JSON status output, and copy/paste verification URLs.

## Files included

```text
public_html/gov.cabnet.app/ops/dev-accelerator.php
docs/BOLT_DEV_ACCELERATOR.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Exact upload paths

Upload these files to:

```text
public_html/gov.cabnet.app/ops/dev-accelerator.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/dev-accelerator.php

docs/BOLT_DEV_ACCELERATOR.md
→ repo docs only / optional documentation copy

HANDOFF.md
→ repo root HANDOFF.md

CONTINUE_PROMPT.md
→ repo root CONTINUE_PROMPT.md

PATCH_README.md
→ repo root PATCH_README.md or keep locally as patch notes
```

## SQL to run

None.

## Verification URLs

```text
https://gov.cabnet.app/ops/dev-accelerator.php
https://gov.cabnet.app/ops/dev-accelerator.php?format=json
https://gov.cabnet.app/ops/bolt-api-visibility.php
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/ops/readiness.php
```

## Verification command

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/dev-accelerator.php
```

## Expected result

- `/ops/dev-accelerator.php` loads.
- The page shows readiness/passport status.
- The page exposes fast capture buttons for accepted, pickup/waiting, started, and completed ride states.
- Default page load does not call Bolt.
- Capture buttons run only the existing Bolt visibility dry-run diagnostic.
- No live EDXEIX submission is enabled.
- No queue jobs are staged.
- No mappings are modified.

## Git commit title

```text
Add Bolt dev accelerator cockpit
```

## Git commit description

```text
Adds a safe operator/development cockpit for the next real future Bolt ride test. The page consolidates readiness status, dry-run visibility capture buttons, auto-watch links, JSON output, and verification URLs while keeping live EDXEIX submission disabled.
```
