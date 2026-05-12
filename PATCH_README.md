# gov.cabnet.app Patch — Phase 66 Lessor 2307 Starting Point Override

## What changed

Adds an idempotent SQL migration documenting and applying a lessor-specific starting point override:

```text
EDXEIX lessor 2307 → EDXEIX starting point 6467495
```

This resolves the dry-run blocker:

```text
lessor_specific_starting_point_not_verified
```

for rides mapped to lessor `2307` when the resolver returns starting point `6467495`.

## Files included

```text
gov.cabnet.app_sql/2026_05_12_phase66_lessor_2307_starting_point_override.sql
docs/PHASE66_LESSOR_2307_STARTING_POINT_OVERRIDE.md
PATCH_README.md
```

## Exact upload paths

```text
gov.cabnet.app_sql/2026_05_12_phase66_lessor_2307_starting_point_override.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_12_phase66_lessor_2307_starting_point_override.sql
```

Keep docs in the local GitHub Desktop repo:

```text
docs/PHASE66_LESSOR_2307_STARTING_POINT_OVERRIDE.md
PATCH_README.md
```

## SQL to run

The live SQL was already applied manually via phpMyAdmin and inserted row ID `4`.

For reproducible deployment/history, the included SQL is idempotent and can be run safely; it will not duplicate an active matching override:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_phase66_lessor_2307_starting_point_override.sql
```

## Verification commands

```bash
mysql -u cabnet_gov -p cabnet_gov -e "SELECT id, edxeix_lessor_id, internal_key, label, edxeix_starting_point_id, is_active, updated_at FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = '2307' ORDER BY id ASC;"
```

Expected:

```text
edxeix_lessor_id: 2307
internal_key: edra_mas
edxeix_starting_point_id: 6467495
is_active: 1
```

## Verification URL

```text
https://gov.cabnet.app/ops/mobile-submit-trial-run.php
```

Paste the same old Bolt ride-details email and run the trial.

Expected remaining blockers:

```text
pickup_not_future
preflight_pickup_not_future
```

Expected removed blocker:

```text
lessor_specific_starting_point_not_verified
```

## Expected result

The old ride remains blocked, live submit remains blocked, and the lessor-specific starting point risk for lessor `2307` is resolved.

## Git commit title

```text
Add lessor 2307 starting point override
```

## Git commit description

```text
Adds Phase 66 SQL and documentation for the lessor-specific EDXEIX starting point override mapping lessor 2307 to starting point 6467495. The change resolves the mobile submit dry-run starting-point governance blocker while keeping live EDXEIX submission disabled and leaving the production pre-ride tool unchanged.
```
