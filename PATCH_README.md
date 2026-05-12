# gov.cabnet.app Patch — Phase 63 Capture Compatibility + Field Extraction Guide

## What changed

Adds an additive SQL compatibility migration for the sanitized EDXEIX submit capture table.

The current capture writer stores canonical columns:

```text
form_action_host
form_action_path
required_field_names_json
map_address_field_name
map_lat_field_name
map_lng_field_name
```

Some readiness/dashboard pages still read compatibility columns:

```text
action_host
action_path
required_field_names
coordinate_field_names
```

This patch adds those compatibility columns and triggers so future sanitized capture rows populate both naming styles.

Also adds a documentation guide with a browser-console extractor that outputs field names only, not values.

## Files included

```text
gov.cabnet.app_sql/2026_05_12_phase63_capture_compat_aliases.sql
docs/EDXEIX_CAPTURE_FIELD_NAME_EXTRACTION.md
PATCH_README.md
```

## Exact upload paths

Upload/copy:

```text
gov.cabnet.app_sql/2026_05_12_phase63_capture_compat_aliases.sql
```

To:

```text
/home/cabnet/gov.cabnet.app_sql/2026_05_12_phase63_capture_compat_aliases.sql
```

Keep docs in the GitHub Desktop repo:

```text
docs/EDXEIX_CAPTURE_FIELD_NAME_EXTRACTION.md
PATCH_README.md
```

The docs file does not need to be uploaded to the public webroot unless you want it on-server.

## SQL to run

Run once:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_phase63_capture_compat_aliases.sql
```

## Verification commands

Confirm compatibility columns exist:

```bash
mysql -u cabnet_gov -p cabnet_gov -e "SHOW COLUMNS FROM ops_edxeix_submit_captures LIKE 'action_host'; SHOW COLUMNS FROM ops_edxeix_submit_captures LIKE 'action_path'; SHOW COLUMNS FROM ops_edxeix_submit_captures LIKE 'coordinate_field_names'; SHOW COLUMNS FROM ops_edxeix_submit_captures LIKE 'required_field_names';"
```

Confirm triggers exist:

```bash
mysql -u cabnet_gov -p cabnet_gov -e "SHOW TRIGGERS LIKE 'ops_edxeix_submit_captures'\G"
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-capture.php
```

Save a real sanitized capture using actual EDXEIX field names only.
Do not save placeholders.
Do not paste token values, cookies, sessions, passwords, credentials, or passenger data.

Then open:

```text
https://gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
```

## Expected result

After a real sanitized capture is saved:

```text
QA summary: 6/6
CAPTURE READY
LIVE SUBMIT BLOCKED
```

## Safety contract

This patch does not:

- call EDXEIX
- call Bolt
- call AADE
- submit anything
- modify the production pre-ride tool
- store cookies, session values, CSRF token values, passwords, credentials, or private config values

Live EDXEIX submit remains blocked.

## Git commit title

```text
Add EDXEIX capture compatibility aliases
```

## Git commit description

```text
Adds an additive Phase 63 SQL migration for sanitized EDXEIX submit capture compatibility columns and triggers. The migration keeps legacy/readiness capture readers aligned with the canonical capture writer columns without enabling live submit. Also adds a field-name extraction guide for safely collecting EDXEIX form metadata without token values, cookies, credentials, or private data.
```
