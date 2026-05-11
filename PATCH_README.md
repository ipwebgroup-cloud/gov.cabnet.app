# gov.cabnet.app patch — Ops UI Shell Phase 10 Firefox Helper Center

Upload changed files only.

## Files included

- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/firefox-extension.php`
- `docs/OPS_UI_SHELL_PHASE10_FIREFOX_CENTER_2026_05_11.md`

## Upload paths

- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css` → `/home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `public_html/gov.cabnet.app/ops/_shell.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/firefox-extension.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/firefox-extension.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/firefox-extension.php
```

Open:

- `https://gov.cabnet.app/ops/firefox-extension.php`

Expected:

- Page requires login.
- Page displays inside the uniform shell.
- Helper file status appears.
- ZIP download works if helper files exist.
- Production pre-ride tool remains unchanged.
