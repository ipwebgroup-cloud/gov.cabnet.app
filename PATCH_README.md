# Patch: Guarded Mapping Editor

## What changed

Adds safe POST editing to `/ops/mappings.php` for EDXEIX mapping IDs only.

## Files

- `public_html/gov.cabnet.app/ops/mappings.php`
- `gov.cabnet.app_sql/2026_04_25_mapping_update_audit.sql`
- `docs/MAPPING_EDITOR.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

```text
public_html/gov.cabnet.app/ops/mappings.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mappings.php

gov.cabnet.app_sql/2026_04_25_mapping_update_audit.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_04_25_mapping_update_audit.sql
```

## SQL

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_04_25_mapping_update_audit.sql
```

## Verification

```text
https://gov.cabnet.app/ops/mappings.php?view=unmapped
https://gov.cabnet.app/ops/mappings.php?format=json
```

The editor should show enabled only after the audit table exists.

## Safety

No Bolt request, EDXEIX request, job creation, booking modification, raw payload modification, or live submission behavior is introduced.
