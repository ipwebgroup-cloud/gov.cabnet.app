# gov.cabnet.app Handoff — v4.5.1

Current posture: safe automated dry-run mode. Live EDXEIX submit remains OFF.

Latest patch: v4.5.1 Bolt Driver Directory Email Sync.

Change: driver email copies no longer require manual name/plate mappings in config. The system syncs Bolt driver directory data from the existing Bolt API connection into `mapping_drivers.driver_email`, then uses that email when a real Bolt pre-ride email is imported.

Safety remains:
- no EDXEIX POST
- no submission_jobs from mail intake/driver notifications
- no submission_attempts from mail intake/driver notifications
- synthetic/test emails suppressed from driver delivery

Required install steps:
1. Upload patch files.
2. Run `2026_05_07_bolt_mail_driver_notifications.sql` if v4.5 was not already installed.
3. Run `2026_05_07_bolt_driver_directory_email_columns.sql`.
4. Add the `mail.driver_notifications` config block with directory mode enabled and manual arrays empty.
5. Run `/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php --hours=720`.
6. Verify `/ops/mail-driver-notifications.php?key=INTERNAL_API_KEY`.

Suggested optional cron:

```cron
*/15 * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php --hours=720 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_driver_directory_sync.log 2>&1
```
