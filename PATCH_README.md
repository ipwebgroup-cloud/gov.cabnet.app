# gov.cabnet.app patch — Ops UI Shell Phase 12 Mobile Compatibility

## What changed

Adds a read-only mobile compatibility guidance page and updates the shared shell navigation/CSS.

## Files included

- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/mobile-compatibility.php`
- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `docs/OPS_UI_SHELL_PHASE12_MOBILE_COMPATIBILITY_2026_05_11.md`
- `PATCH_README.md`

## Upload paths

- `public_html/gov.cabnet.app/ops/_shell.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/mobile-compatibility.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/mobile-compatibility.php`
- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css` → `/home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-compatibility.php
```

Open:

- `https://gov.cabnet.app/ops/mobile-compatibility.php`

## Safety

This patch does not modify the production pre-ride tool and does not call Bolt, EDXEIX, or AADE.
