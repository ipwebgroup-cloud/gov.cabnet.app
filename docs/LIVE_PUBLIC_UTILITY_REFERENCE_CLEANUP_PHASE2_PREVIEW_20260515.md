# Live Public Utility Reference Cleanup — Phase 2 Preview

Date: 2026-05-15
Version: v3.0.88-public-utility-reference-cleanup-phase2-preview

## Purpose

Adds a read-only Phase 2 preview scanner for the remaining references to the guarded public-root Bolt/EDXEIX utility endpoints.

The scanner separates actionable references from inventory/audit/planner references so future cleanup does not chase intentional documentation inside route inventory and audit tools.

## Safety

- No route moves.
- No route deletion.
- No database connection.
- No filesystem writes.
- No Bolt call.
- No EDXEIX call.
- No AADE call.
- Production pre-ride tool is untouched.

## New files

- `/home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/public-utility-reference-cleanup-phase2-preview.php`

## Use

Run the CLI JSON report:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php --json"
```

Open the ops page after login:

```text
https://gov.cabnet.app/ops/public-utility-reference-cleanup-phase2-preview.php
```

## Next step

Use the safe Phase 2 candidate list to update docs and ops/admin links first. Do not move or delete the legacy public-root utilities until compatibility wrappers and quiet-period evidence are complete.
