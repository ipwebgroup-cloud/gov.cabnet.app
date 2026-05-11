# Ops UI Shell Phase 5 SQL Hotfix — 2026-05-11

Fixes the SQL syntax error shown on:

- `/ops/audit-log.php`
- `/ops/login-attempts.php`

Cause:

- The Phase 5 pages used `SHOW TABLES LIKE ?` with a prepared statement.
- Some MariaDB/MySQL versions do not allow parameter markers in `SHOW TABLES LIKE`, causing: `SQL syntax ... near '?' at line 1`.

Fix:

- Replaces the table-existence check with a prepared `information_schema.TABLES` query.
- Keeps both pages admin-only and read-only.
- Does not touch the production pre-ride email tool.
- Does not call Bolt, EDXEIX, or AADE.
- Does not write database rows.
