# Ops UI Shell + Profile — 2026-05-11

Adds a non-disruptive shared `/ops` GUI foundation based on the current EDXEIX-style interface.

## Production safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not write database rows.
- Does not enable live EDXEIX submission.

## New files

- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/profile.php`
- `public_html/gov.cabnet.app/ops/ui-shell-preview.php`
- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`

## Purpose

The shared shell provides a consistent top bar, sidebar, profile area, navigation, tabs, safety notice, and reusable profile/user display helpers.

Future pages can include:

```php
require_once __DIR__ . '/_shell.php';
opsui_shell_begin([...]);
// page content
opsui_shell_end();
```

## Next recommended step

Build `/ops/pre-ride-email-toolv2.php` later using this shell, test it separately, and only then decide whether to merge changes back into the production tool.
