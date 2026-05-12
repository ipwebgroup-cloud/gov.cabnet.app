# gov.cabnet.app — Phase 35 Mapping Governance

## Upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/company-mapping-detail.php
public_html/gov.cabnet.app/ops/starting-point-control.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/company-mapping-detail.php
/home/cabnet/public_html/gov.cabnet.app/ops/starting-point-control.php
```

## SQL

None.

Uses existing tables:

```text
mapping_lessor_starting_points
mapping_starting_points
mapping_drivers
mapping_vehicles
edxeix_export_lessors
edxeix_export_starting_points
ops_audit_log optional
```

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/company-mapping-detail.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/starting-point-control.php
```

Open:

```text
https://gov.cabnet.app/ops/company-mapping-detail.php?lessor=1756
https://gov.cabnet.app/ops/starting-point-control.php?lessor=1756
```

Expected:

- Login required.
- Detail page is read-only.
- Starting Point Control shows WHITEBLUE / 1756 override as 612164.
- Admin users can add/update/deactivate lessor-specific starting point override rows.
- Production pre-ride tool remains unchanged.
