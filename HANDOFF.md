# gov.cabnet.app Bolt → EDXEIX Bridge — Handoff after v4.4.1

Current state: safe automated dry-run mode.

- Mail intake cron is active.
- Auto dry-run evidence cron is active.
- Live EDXEIX submit remains OFF.
- `app.dry_run = true`.
- `edxeix.live_submit_enabled = false`.
- Canonical `edxeix.future_start_guard_minutes = 2` in `/home/cabnet/gov.cabnet.app_config/config.php`.

## v4.4.1 hotfix

The raw endpoint `/bolt_edxeix_preflight.php` was still showing `guard_minutes = 30` because the legacy helper/config path can see an older split config fallback. The mail dashboards and auto dry-run flow were already showing `FUTURE GUARD 2 MIN` correctly.

v4.4.1 updates only the raw preflight endpoint so it reads the guard directly from canonical server config:

- `/home/cabnet/gov.cabnet.app_config/config.php`

The raw JSON endpoint should now show:

- top-level `guard_minutes = 2`
- row-level `future_guard_minutes = 2`
- preview mapping status `future_guard_minutes = 2`

## Safety

v4.4.1 does not create jobs, attempts, or live EDXEIX submissions. It is a read-only display/alignment hotfix.

## Continue from here

1. Upload v4.4.1 changed file.
2. Run PHP syntax check.
3. Open `/bolt_edxeix_preflight.php?limit=30` and confirm all guard values show `2`.
4. Continue monitoring the next real future Bolt email.
5. Do not enable live submit until explicitly approved.
