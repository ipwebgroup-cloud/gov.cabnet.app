# Ops UI Shell Phase 34 — Company Mapping Control — 2026-05-12

## Purpose

Adds a read-only company/lessor mapping governance page:

```text
/ops/company-mapping-control.php
```

This page is designed to prevent the class of issue found during the WHITEBLUE / Georgios Tsatsas / XZO1837 verification, where the correct lessor/driver/vehicle were resolved but the starting point fell back to the wrong global starting point.

## Safety

This patch is read-only.

It does not:

- modify `/ops/pre-ride-email-tool.php`
- call Bolt
- call EDXEIX
- call AADE
- write database rows
- stage jobs
- enable live EDXEIX submission
- expose secrets

## Tables checked

The page checks, when available:

- `edxeix_export_lessors`
- `edxeix_export_drivers`
- `edxeix_export_vehicles`
- `edxeix_export_starting_points`
- `mapping_drivers`
- `mapping_vehicles`
- `mapping_starting_points`
- `mapping_lessor_starting_points`

## Key rule

Every operational lessor should have an explicit lessor-specific starting point row in:

```text
mapping_lessor_starting_points
```

Global starting points in:

```text
mapping_starting_points
```

should be treated as fallback only.

## Verified expectation included

The page currently includes the verified live EDXEIX expectation:

```text
WHITEBLUE / lessor 1756
→ starting point 612164
→ Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600
```

This was visually verified in live EDXEIX on 2026-05-12.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/company-mapping-control.php
```

URL:

```text
https://gov.cabnet.app/ops/company-mapping-control.php
```

Optional detail view:

```text
https://gov.cabnet.app/ops/company-mapping-control.php?lessor=1756
```
