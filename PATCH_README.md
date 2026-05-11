# gov.cabnet.app — Ops UI Shell Phase 6 Layout Hotfix

## What changed

- Fixes horizontal page drift/overflow on profile/activity pages.
- Keeps wide tables scrollable inside `.table-wrap` instead of pushing the full page sideways.
- Updates `_shell.php` to load `/assets/css/gov-ops-shell.css?v=1.6` for cache busting.

## Files included

- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `docs/OPS_UI_SHELL_PHASE6_LAYOUT_HOTFIX_2026_05_11.md`
- `PATCH_README.md`

## Upload paths

- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css` → `/home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `public_html/gov.cabnet.app/ops/_shell.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`

## SQL to run

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Then open:

- `https://gov.cabnet.app/ops/profile-activity.php`
- `https://gov.cabnet.app/ops/activity-center.php`
- `https://gov.cabnet.app/ops/audit-log.php`
- `https://gov.cabnet.app/ops/login-attempts.php`

Expected:

- no full-page horizontal drift
- wide tables scroll inside their card only
- topbar/sidebar remain stable
- production pre-ride tool remains unchanged

## Git commit title

Fix ops shell horizontal overflow

## Git commit description

Fixes horizontal page overflow in the shared ops UI shell by containing grid/card widths and keeping wide log tables scrollable inside table wrappers. Updates the shell CSS cache-busting version.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, workflow writes, queue staging, or live submission behavior are added.
