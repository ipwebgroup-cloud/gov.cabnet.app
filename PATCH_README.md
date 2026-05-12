# gov.cabnet.app Patch — Phase 65 Capture Field Alias Fix

## What changed

Adds an additive SQL migration to fix EDXEIX sanitized capture compatibility metadata.

The previous compatibility trigger stripped square brackets from field names such as:

```text
lessee[type]
lessee[name]
lessee[vat_number]
lessee[legal_representative]
```

The SQL recreates the triggers so bracketed field names are preserved, adds a `select_field_names` compatibility alias, and normalizes the latest sanitized `/dashboard/lease-agreement` capture so optional fields do not block dry-run validation.

## Files included

```text
gov.cabnet.app_sql/2026_05_12_phase65_capture_field_alias_fix.sql
docs/EDXEIX_CAPTURE_FIELD_ALIAS_FIX.md
PATCH_README.md
```

## Exact upload paths

```text
gov.cabnet.app_sql/2026_05_12_phase65_capture_field_alias_fix.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_12_phase65_capture_field_alias_fix.sql
```

Keep these in the local GitHub Desktop repo:

```text
docs/EDXEIX_CAPTURE_FIELD_ALIAS_FIX.md
PATCH_README.md
```

## SQL to run

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_phase65_capture_field_alias_fix.sql
```

## Verification commands

```bash
mysql -u cabnet_gov -p cabnet_gov -e "SHOW COLUMNS FROM ops_edxeix_submit_captures LIKE 'select_field_names';"

mysql -u cabnet_gov -p cabnet_gov -e "SELECT id, required_field_names, select_field_names, coordinate_field_names, updated_at FROM ops_edxeix_submit_captures ORDER BY id DESC LIMIT 1\G"
```

Expected: `required_field_names` preserves `lessee[type]` and `lessee[name]`; `select_field_names` contains `lessor`, `lessee[type]`, `driver`, `vehicle`, `starting_point_id`.

Then re-run:

```text
https://gov.cabnet.app/ops/mobile-submit-trial-run.php
```

Use the same old Bolt ride-details email. Expected result remains blocked because the ride is old and lessor `2307` still needs lessor-specific starting-point verification, but the incorrect missing bracketed-field blockers should disappear.

## Expected result

- Capture metadata preserves bracketed EDXEIX field names.
- Select/dropdown field metadata is visible to dry-run validation.
- Live submit remains blocked.
- Production pre-ride tool remains unchanged.
- Lessor `2307` starting-point override remains a separate governance task and is not added by this patch.

## Git commit title

```text
Fix EDXEIX capture field aliases
```

## Git commit description

```text
Adds Phase 65 SQL to preserve bracketed EDXEIX field names in sanitized capture compatibility aliases, expose select/dropdown field names, and normalize the latest lease-agreement capture metadata so optional fields do not incorrectly block dry-run validation. This is metadata-only and does not enable live EDXEIX submission or modify the production pre-ride tool.
```
