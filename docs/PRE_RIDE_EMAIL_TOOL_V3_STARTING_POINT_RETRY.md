# V3 Starting Point Retry Patch

This V3-only patch updates the isolated Firefox helper used by `gov.cabnet.app` for EDXEIX form fill.

## Purpose

The first live V3 fill test reached EDXEIX and filled company, passenger, driver, vehicle, addresses, dates, and price, but the legal starting-point dropdown remained unselected.

This patch adds stronger V3-only starting-point handling:

- finds the starting-point select by multiple possible field names,
- falls back to nearby label detection for `Σημείο έναρξης`,
- waits/retries while EDXEIX loads options after company selection,
- matches option values by exact value and embedded numeric IDs,
- matches option text by available labels,
- safely uses the single available option only when the dropdown has exactly one real option,
- includes starting-point options in the copied diagnostic.

## Safety

The helper remains fill-only.

No EDXEIX POST/save button is added.
No AADE call is added.
No production helper or production pre-ride tool is modified.

## Files

- `tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js`
- `tools/firefox-edxeix-autofill-helper-v3/manifest.json`

## Verify

Reload the temporary V3 Firefox extension from:

`tools/firefox-edxeix-autofill-helper-v3/manifest.json`

Open EDXEIX through the V3 queue dashboard and click:

`Fill using V3 exact IDs`

If the starting point still does not select, click:

`Copy/report V3 diagnostic`

The diagnostic now includes available starting-point options.
