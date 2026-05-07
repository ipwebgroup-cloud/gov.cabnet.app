Greetings Sophion. Continue the gov.cabnet.app Bolt → EDXEIX bridge from the v4.4.1 state.

Current state:
- Safe automated dry-run mode.
- Mail intake cron ON.
- Auto dry-run evidence cron ON.
- Live EDXEIX submit OFF.
- `app.dry_run = true`.
- `edxeix.live_submit_enabled = false`.
- Canonical `edxeix.future_start_guard_minutes = 2` in `/home/cabnet/gov.cabnet.app_config/config.php`.

Most recent patch:
- v4.4.1 raw preflight guard alignment.
- `/bolt_edxeix_preflight.php` now reads the guard directly from canonical config.php so raw JSON should show `guard_minutes = 2` instead of legacy fallback `30`.
- This patch is read-only and does not create submission jobs, attempts, or EDXEIX POSTs.

Next safest step:
- Verify `/bolt_edxeix_preflight.php?limit=30` shows top-level and row-level guard values as `2`.
- Monitor the next real future Bolt email and confirm auto dry-run evidence creation.
- Do not enable live submit unless Andreas explicitly requests a live-submit patch.
