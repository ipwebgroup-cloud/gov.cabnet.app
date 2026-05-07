# gov.cabnet.app Bolt → EDXEIX Bridge Handoff

Current bridge posture: safe automated dry-run mode. Live EDXEIX submit remains OFF.

Latest patch: v4.5.2 Driver Identity Email Resolution.

Important behavior:
- Mail intake imports Bolt pre-ride emails into `bolt_mail_intake`.
- Auto dry-run can create local `normalized_bookings source='bolt_mail'` and `bolt_mail_dry_run_evidence` only for valid future candidates.
- Driver email copies are optional and audited in `bolt_mail_driver_notifications`.
- Driver email recipient resolution must use Bolt driver identity/name from `mapping_drivers.driver_email`.
- Vehicle plate must not be used to select the recipient because drivers may change cars.
- No `submission_jobs`, no `submission_attempts`, and no EDXEIX POST unless explicitly approved in a later live-submit patch.

Next validation:
1. Confirm config `mail.driver_notifications.enabled=true` after server config is saved.
2. Run `/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php --hours=720`.
3. Confirm `mapping_drivers.external_driver_name` and `driver_email` are populated.
4. Test with a real future Bolt pre-ride email.
