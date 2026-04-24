# Sanitization Report

This package was generated from the uploaded project state with known secrets/configs removed or replaced by placeholders.

## Excluded intentionally

- `gov.cabnet.app_config/config.php` real server config
- `gov.cabnet.app_config/bolt.php` real Bolt override config
- `cabnet_gov_current_SQLbd.sql` raw data dump with live Bolt/order data
- temporary public cleanup/path-check/schema/lab scripts
- stale static `ops/index.html`
- runtime logs/sessions/artifacts

## Included safely

- `gov.cabnet.app_config/config.php.example`
- `gov.cabnet.app_config/bolt.php.example`
- `gov.cabnet.app_sql/current_schema_sanitized.sql` schema-only export with all INSERT/data rows removed
- `gov.cabnet.app_sql/2026_04_24_verified_edxeix_mappings.sql` as a placeholder template only

## Scan result

Known credential strings from the uploaded archive were checked and are absent from the package. The package still deserves normal manual review before a public GitHub push.
