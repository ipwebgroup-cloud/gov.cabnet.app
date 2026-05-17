# gov.cabnet.app patch — Admin Excluded Sprinter mapping labels

## What changed

This patch marks Mercedes-Benz Sprinter / EMT8640 as `Admin Excluded` wherever it appears in the mapping section and strengthens the central exemption detector.

Operational rule preserved:

- No invoicing.
- No AADE/myDATA invoice/receipt.
- No driver email.
- No voucher / receipt-copy email.
- No automated EDXEIX processing.

## Files included

```text
gov.cabnet.app_app/src/Domain/VehicleExemptionService.php
public_html/gov.cabnet.app/ops/mapping-workbench-v3.php
public_html/gov.cabnet.app/ops/mappings.php
public_html/gov.cabnet.app/ops/mapping-center.php
public_html/gov.cabnet.app/ops/_mapping_nav.php
docs/ADMIN_EXCLUDED_SPRINTER_MAPPING_LABELS_2026_05_17.md
PATCH_README.md
```

## Upload paths

Upload:

```text
gov.cabnet.app_app/src/Domain/VehicleExemptionService.php
```

to:

```text
/home/cabnet/gov.cabnet.app_app/src/Domain/VehicleExemptionService.php
```

Upload:

```text
public_html/gov.cabnet.app/ops/mapping-workbench-v3.php
public_html/gov.cabnet.app/ops/mappings.php
public_html/gov.cabnet.app/ops/mapping-center.php
public_html/gov.cabnet.app/ops/_mapping_nav.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/
```

Upload:

```text
docs/ADMIN_EXCLUDED_SPRINTER_MAPPING_LABELS_2026_05_17.md
```

to the repository docs folder.

## SQL

No SQL is required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Domain/VehicleExemptionService.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-workbench-v3.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mappings.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-center.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_mapping_nav.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/mapping-workbench-v3.php?view=needs_map&q=EMT8640
https://gov.cabnet.app/ops/mapping-workbench-v3.php?view=needs_map&q=Sprinter
https://gov.cabnet.app/ops/mappings.php?q=EMT8640
https://gov.cabnet.app/ops/mappings.php?q=Sprinter
https://gov.cabnet.app/ops/mapping-workbench-v3.php?format=json&q=EMT8640
https://gov.cabnet.app/ops/mappings.php?format=json&q=EMT8640
```

## Expected result

Rows for EMT8640 / Mercedes-Benz Sprinter show a red `Admin Excluded` label.

Sanitized JSON includes:

```json
"admin_excluded": true
```

## Safety

This patch does not call Bolt, submit to EDXEIX, call AADE, create queue jobs, change live-submit gates, delete data, or expose secrets.

## Git commit title

```text
Show Admin Excluded Sprinter in mapping pages
```

## Git commit description

```text
Marked Mercedes-Benz Sprinter / EMT8640 as Admin Excluded across mapping pages and expanded the central vehicle exemption service to detect the Sprinter model/name in addition to the existing EMT8640 plate and Bolt vehicle identifier.

The mapping workbench and legacy mapping editor now display Admin Excluded badges and include admin_excluded metadata in sanitized JSON outputs.

This preserves the permanent operational rule: no invoicing, no AADE receipt/invoice, no driver email, no voucher/receipt-copy email, and no automated EDXEIX processing for this vehicle.
```
