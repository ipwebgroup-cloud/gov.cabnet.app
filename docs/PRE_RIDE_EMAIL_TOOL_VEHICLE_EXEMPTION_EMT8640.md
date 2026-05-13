# EMT8640 Permanent Vehicle Exemption

Vehicle: `EMT8640`  
Bolt vehicle identifier: `f9170acc-3bc4-43c5-9eed-65d9cadee490`

Operational rule:

- No voucher / receipt-copy email.
- No driver email.
- No AADE/myDATA invoice or official receipt.
- No EDXEIX worker submission.
- No V3 queue intake.

## Implementation

A central class is added:

```text
gov.cabnet.app_app/src/Domain/VehicleExemptionService.php
```

An idempotent patcher inserts guards into the existing production and V3 code paths:

```text
gov.cabnet.app_app/cli/apply_emt8640_vehicle_exemption_patch.php
```

Targeted guards:

```text
gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
gov.cabnet.app_app/src/Domain/SubmissionService.php
gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
```

## Safety

This patch does not touch:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

No credentials are added. No EDXEIX or AADE calls are made by the patcher.
