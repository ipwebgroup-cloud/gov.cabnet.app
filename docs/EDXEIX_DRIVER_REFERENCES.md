# EDXEIX Driver Reference Notes

This update adds a reference-only panel to `/ops/mappings.php` for currently known EDXEIX driver dropdown values.

## Known references

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

## Safety posture

These references are notes only. They do not automatically update any Bolt mapping row.

The guarded mapping editor still requires:

- ops access guard authorization;
- POST request;
- positive numeric EDXEIX ID;
- exact confirmation phrase;
- local audit table;
- local audit row for each change.

## Current operating note

Leave Georgios Zachariou unmapped for now unless his exact EDXEIX driver ID is independently confirmed.

## Verification

Open:

```text
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/mappings.php?format=json
```

Expected:

- HTML shows the Known EDXEIX driver references panel.
- JSON includes `known_edxeix_driver_references`.
- JSON remains sanitized and does not include `raw_payload_json`.
- No Bolt request, EDXEIX request, database write, queue creation, or live submission occurs from a GET request.
