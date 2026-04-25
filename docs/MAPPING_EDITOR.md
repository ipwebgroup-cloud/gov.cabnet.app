# Mapping Editor

Adds guarded manual editing for Bolt → EDXEIX mapping IDs from `/ops/mappings.php`.

## Scope

The editor can update only these fields:

- `mapping_drivers.edxeix_driver_id`
- `mapping_vehicles.edxeix_vehicle_id`

It does not update Bolt UUIDs, names, plates, raw payloads, bookings, jobs, attempts, or any EDXEIX remote system.

## Safety

- Requires the operations access guard.
- Requires POST.
- Requires positive numeric EDXEIX ID values.
- Requires exact confirmation phrase per row:
  - `UPDATE DRIVER MAPPING`
  - `UPDATE VEHICLE MAPPING`
- Requires `mapping_update_audit` table to exist before edits are accepted.
- Writes an audit row for each successful change.
- GET and JSON views remain operational/sanitized.

## Migration

Run once:

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_04_25_mapping_update_audit.sql
```

Verify:

```bash
mysql cabnet_gov -e "SHOW TABLES LIKE 'mapping_update_audit';"
```

## Verification

Open:

```text
https://gov.cabnet.app/ops/mappings.php?view=unmapped
```

Try updating one known EDXEIX ID using the correct confirmation phrase.

Then verify audit rows:

```bash
mysql cabnet_gov -e "SELECT id, table_name, row_id, field_name, old_value, new_value, created_at FROM mapping_update_audit ORDER BY id DESC LIMIT 10;"
```

## Non-goals

This patch does not enable live EDXEIX submission.
