# gov.cabnet.app — Ops UI Shell Phase 5 SQL Hotfix

## What changed

Fixes the MariaDB SQL syntax error on the Phase 5 read-only admin pages:

- `public_html/gov.cabnet.app/ops/audit-log.php`
- `public_html/gov.cabnet.app/ops/login-attempts.php`

The error was caused by `SHOW TABLES LIKE ?` using a prepared statement. The pages now use `information_schema.TABLES` with a bound `TABLE_NAME` parameter.

## Production safety

This patch does not modify:

- `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

This patch does not call Bolt, EDXEIX, or AADE. It does not stage jobs, write workflow data, or enable live submission.

## Upload paths

Upload:

- `public_html/gov.cabnet.app/ops/audit-log.php`
- `public_html/gov.cabnet.app/ops/login-attempts.php`

To:

- `/home/cabnet/public_html/gov.cabnet.app/ops/audit-log.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/login-attempts.php`

## SQL to run

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/audit-log.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/login-attempts.php
```

Then open:

- `https://gov.cabnet.app/ops/audit-log.php`
- `https://gov.cabnet.app/ops/login-attempts.php`

Expected: pages load without the SQL syntax error and show activity/login rows or an empty table.
