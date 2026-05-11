# Ops UI Shell Phase 8 — User Preferences — 2026-05-11

Adds a safe self-service preferences page for the logged-in operator.

Files:
- `public_html/gov.cabnet.app/ops/profile-preferences.php`
- `public_html/gov.cabnet.app/ops/profile.php`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `gov.cabnet.app_sql/2026_05_11_ops_user_preferences.sql`

Safety:
- Production pre-ride email tool is not modified.
- No Bolt, EDXEIX, or AADE calls.
- No workflow writes or queue staging.
- Preferences are stored only for the current logged-in user.

Run SQL before saving preferences:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_11_ops_user_preferences.sql
```
