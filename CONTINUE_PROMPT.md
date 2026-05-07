Continue gov.cabnet.app Bolt → EDXEIX bridge from v4.5.1.

Important state:
- Live EDXEIX submit OFF.
- Dry-run ON.
- Mail intake cron ON.
- Auto dry-run cron ON.
- Driver email copy layer added.
- v4.5.1 supersedes manual driver email config. Use Bolt driver directory API sync into `mapping_drivers.driver_email`.

Next verification:
1. Run v4.5/v4.5.1 SQL migrations.
2. Run `sync_bolt_driver_directory.php --hours=720`.
3. Confirm `mapping_drivers.driver_email` has rows.
4. Enable `mail.driver_notifications.enabled=true` in server-only config.
5. Send a real future Bolt pre-ride email.
6. Confirm importer log shows `driver_sent=1` for mapped driver, with `submission_jobs=0` and `submission_attempts=0`.

Never enable live EDXEIX submission unless Andreas explicitly asks.
