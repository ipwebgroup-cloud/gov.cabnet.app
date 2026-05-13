# gov.cabnet.app patch — EMT8640 Permanent Vehicle Exemption

## What changed

Adds a central permanent exemption for vehicle `EMT8640` and Bolt vehicle identifier `f9170acc-3bc4-43c5-9eed-65d9cadee490`.

The intended operational behavior is:

```text
No voucher
No driver email
No invoice / AADE receipt
No EDXEIX worker submission
No V3 queue intake
```

## Files included

```text
gov.cabnet.app_app/src/Domain/VehicleExemptionService.php
gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php
docs/PRE_RIDE_EMAIL_TOOL_VEHICLE_EXEMPTION_EMT8640.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/Domain/VehicleExemptionService.php
→ /home/cabnet/gov.cabnet.app_app/src/Domain/VehicleExemptionService.php

gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php
→ /home/cabnet/gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php
```

Docs are for the local repo.

## Apply on server

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Domain/VehicleExemptionService.php
php -l /home/cabnet/gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php

php /home/cabnet/gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php --dry-run
php /home/cabnet/gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php --apply

php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
php -l /home/cabnet/gov.cabnet.app_app/src/Domain/SubmissionService.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
```

## Apply in local repo before commit

From the local repo root:

```bash
php gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php --dry-run --root=.
php gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php --apply --root=.
```

If PHP is not available locally, apply on the server first, then copy the four modified files back into the local repo before committing.

## Safety

This patch does not modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

The patcher creates backups under:

```text
/home/cabnet/gov.cabnet.app_app/storage/patch_backups/emt8640_YYYYMMDD_HHMMSS/
```
