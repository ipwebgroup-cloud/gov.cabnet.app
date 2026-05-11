# gov.cabnet.app — Ops UI Shell Phase 8 Preferences

Upload the changed files to their matching server paths.

## Files included

- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/profile.php`
- `public_html/gov.cabnet.app/ops/profile-preferences.php`
- `gov.cabnet.app_sql/2026_05_11_ops_user_preferences.sql`
- `docs/OPS_UI_SHELL_PHASE8_PREFERENCES_2026_05_11.md`

## SQL

Run:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_11_ops_user_preferences.sql
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile-preferences.php
```

Then open:

- `https://gov.cabnet.app/ops/profile.php`
- `https://gov.cabnet.app/ops/profile-preferences.php`

## Production safety

This patch does not modify `/ops/pre-ride-email-tool.php` and does not enable live EDXEIX submission.
