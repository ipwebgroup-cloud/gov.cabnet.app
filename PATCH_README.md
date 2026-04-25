# Patch: EDXEIX driver reference panel

## What changed

Adds a reference-only panel to `/ops/mappings.php` listing known EDXEIX driver IDs:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

The notes are display-only. They do not automatically map any Bolt driver.

## Files included

```text
public_html/gov.cabnet.app/ops/mappings.php
docs/EDXEIX_DRIVER_REFERENCES.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/mappings.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mappings.php
```

## SQL

No SQL required.

## Verification

Open:

```text
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/mappings.php?format=json
```

Expected:

- The mapping page shows a Known EDXEIX driver references panel.
- JSON includes `known_edxeix_driver_references`.
- JSON remains sanitized and excludes `raw_payload_json`.

## Safety

This patch does not call Bolt, does not call EDXEIX, does not write to the database on GET, does not create jobs, and does not enable live submission.
