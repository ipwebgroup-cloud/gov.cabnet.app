# Ops UI Shell Phase 5 — Activity Logs — 2026-05-11

This patch continues the unified `/ops` GUI and adds admin-only read-only visibility pages for the login system.

## Added

- `/ops/audit-log.php`
- `/ops/login-attempts.php`

## Updated

- `/ops/_shell.php` updated to v1.4.
- `/assets/css/gov-ops-shell.css` updated with filter, log table, and status styling.

## Safety

- No change to `/ops/pre-ride-email-tool.php`.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No queue/job staging.
- No database writes from these new pages.
- Admin-only read-only visibility.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/audit-log.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/login-attempts.php
```

Open:

- `https://gov.cabnet.app/ops/audit-log.php`
- `https://gov.cabnet.app/ops/login-attempts.php`
