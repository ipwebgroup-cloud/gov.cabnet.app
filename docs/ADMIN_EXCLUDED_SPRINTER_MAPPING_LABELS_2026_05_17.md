# Admin Excluded Sprinter Mapping Labels — 2026-05-17

## Purpose

Mercedes-Benz Sprinter / EMT8640 is a permanent operational exclusion.

Operational rule:

- No invoicing.
- No AADE/myDATA receipt/invoice.
- No driver email.
- No voucher / receipt-copy email.
- No automated EDXEIX processing.

This patch makes that rule visible in the mapping section and strengthens the central vehicle exemption detector.

## Files changed

- `gov.cabnet.app_app/src/Domain/VehicleExemptionService.php`
- `public_html/gov.cabnet.app/ops/mapping-workbench-v3.php`
- `public_html/gov.cabnet.app/ops/mappings.php`
- `public_html/gov.cabnet.app/ops/mapping-center.php`
- `public_html/gov.cabnet.app/ops/_mapping_nav.php`

## Behavior

### Central guard

`VehicleExemptionService` now treats the following as admin-excluded:

- Plate: `EMT8640`
- Bolt vehicle identifier: `f9170acc-3bc4-43c5-9eed-65d9cadee490`
- Model/name containing: `Mercedes-Benz Sprinter`

The reason text now states the full rule: no driver email, no voucher, no invoice/AADE receipt, and no automated EDXEIX submission.

### Mapping Workbench V3

`/ops/mapping-workbench-v3.php` now:

- detects admin-excluded vehicles by plate, UUID, model, or name;
- shows a red `Admin Excluded` badge in grouped driver + active vehicle rows;
- highlights the row with an admin-excluded warning style;
- exports `admin_excluded` and `admin_exclusion_reason` in sanitized JSON.

### Legacy Mapping Editor

`/ops/mappings.php` now:

- shows `Admin Excluded` beside matching driver active vehicles;
- shows `Admin Excluded` beside matching vehicle rows;
- highlights matching rows;
- exports `admin_excluded` and `admin_exclusion_reason` in sanitized JSON.

### Mapping navigation

`/ops/_mapping_nav.php` now includes an `Admin Exclusions` link to the EMT8640 audit page.

### Mapping Center

`/ops/mapping-center.php` now documents the admin-excluded vehicle rule in the mapping safety policy and includes the Admin Exclusions route.

## Safety

This patch does not:

- call Bolt;
- call EDXEIX;
- call AADE;
- create jobs;
- change live-submit gates;
- delete data;
- expose credentials, cookies, tokens, or sessions.

## Verification URLs

- `https://gov.cabnet.app/ops/mapping-workbench-v3.php?view=needs_map&q=EMT8640`
- `https://gov.cabnet.app/ops/mapping-workbench-v3.php?view=needs_map&q=Sprinter`
- `https://gov.cabnet.app/ops/mappings.php?q=EMT8640`
- `https://gov.cabnet.app/ops/mappings.php?q=Sprinter`
- `https://gov.cabnet.app/ops/mapping-workbench-v3.php?format=json&q=EMT8640`
- `https://gov.cabnet.app/ops/mappings.php?format=json&q=EMT8640`

## Expected result

Anywhere EMT8640 / Mercedes-Benz Sprinter appears in mapping pages, it should show:

`Admin Excluded`

The JSON output should include:

```json
"admin_excluded": true
```

and an `admin_exclusion_reason` explaining that the vehicle must not be invoiced, emailed, or processed automatically.
