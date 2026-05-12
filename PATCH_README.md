# gov.cabnet.app patch — Phase 31 EDXEIX Submit Capture

## What changed

Adds `/ops/edxeix-submit-capture.php` and an additive SQL migration for storing sanitized EDXEIX form research metadata.

## Files included

- `public_html/gov.cabnet.app/ops/edxeix-submit-capture.php`
- `gov.cabnet.app_sql/2026_05_12_ops_edxeix_submit_captures.sql`
- `docs/OPS_UI_SHELL_PHASE31_EDXEIX_SUBMIT_CAPTURE_2026_05_12.md`

## Upload paths

- `public_html/gov.cabnet.app/ops/edxeix-submit-capture.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-capture.php`
- `gov.cabnet.app_sql/2026_05_12_ops_edxeix_submit_captures.sql` → `/home/cabnet/gov.cabnet.app_sql/2026_05_12_ops_edxeix_submit_captures.sql`

## SQL to run

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_ops_edxeix_submit_captures.sql
```

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-capture.php
```

## Verification URL

```text
https://gov.cabnet.app/ops/edxeix-submit-capture.php
```

## Expected result

- login required
- page opens inside shared ops shell
- table readiness displays
- sanitized captures can be saved after SQL migration
- no EDXEIX call is made
- no live submit is enabled
- production pre-ride email tool remains unchanged

## Git commit title

Add EDXEIX submit capture page

## Git commit description

Adds a sanitized EDXEIX submit capture page and additive SQL table for future server-side mobile submit research. The page stores only safe form metadata such as action path, method, field names, and sanitized notes while blocking secrets such as cookies, session values, credentials, and CSRF token values.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, workflow queue staging, or live submission behavior are added.
