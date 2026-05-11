# Ops UI Shell Phase 9 — Apply Preferences — 2026-05-11

This patch applies the operator UI preferences introduced in Phase 8 to shared-shell pages only.

## Safety contract

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not stage jobs.
- Does not write workflow data.
- Does not enable live EDXEIX submission.

## Files

- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/my-start.php`
- `public_html/gov.cabnet.app/ops/profile-preferences.php`
- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`

## Behavior

- `_shell.php` reads `ops_user_preferences` when available.
- Supported shared-shell pages apply:
  - sidebar density
  - table density
  - safety notice visibility
- `/ops/my-start.php` redirects the logged-in user to their selected preferred landing page.
- Normal production URLs remain unchanged.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/my-start.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile-preferences.php
```

Visit:

- `https://gov.cabnet.app/ops/profile-preferences.php`
- `https://gov.cabnet.app/ops/my-start.php`
- `https://gov.cabnet.app/ops/profile-activity.php`

Expected result: preferences apply only on shared-shell pages. The production pre-ride tool route remains unchanged.
