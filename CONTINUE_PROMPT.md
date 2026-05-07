Greetings Sophion. Continue the gov.cabnet.app Bolt → EDXEIX bridge from the v4.5 state.

Current state:
- Safe automated dry-run mode.
- Mail intake cron ON.
- Auto dry-run evidence cron ON.
- Live EDXEIX submit OFF.
- `app.dry_run = true`.
- `edxeix.live_submit_enabled = false`.
- `edxeix.future_start_guard_minutes = 2`.
- v4.4.1 raw preflight guard alignment is validated.

Latest live test:
- Real Bolt pre-ride email imported successfully into `bolt_mail_intake`.
- It was blocked as `blocked_past` because pickup time had already passed by import time.
- `submission_jobs = 0`.
- `submission_attempts = 0`.

v4.5 feature:
- Adds optional driver email copies when new Bolt pre-ride emails are imported.
- Uses `BoltMailDriverNotificationService`.
- Uses SQL table `bolt_mail_driver_notifications`.
- Dashboard: `/ops/mail-driver-notifications.php?key=INTERNAL_API_KEY`.
- Driver notifications require server-only config under `mail.driver_notifications` with real driver email mappings.
- Synthetic/test emails are suppressed.
- No EDXEIX jobs, attempts, or POSTs are created.

Next safest step:
1. Upload v4.5 changed files.
2. Run the SQL migration.
3. Add real driver email mappings server-side only.
4. Enable `mail.driver_notifications.enabled = true`.
5. Send a future Bolt email test and confirm `driver_sent=1`, while `submission_jobs=0` and `submission_attempts=0`.
6. Continue monitoring future-candidate dry-run evidence creation.
