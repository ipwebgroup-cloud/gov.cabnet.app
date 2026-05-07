# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v5.1:

- Dry-run production posture was previously frozen in v4.9.
- Guarded live-submit was armed in v5.0 but remains blocked by `edxeix_session_connected=false` and one-shot locks.
- v5.1 adds a second driver email: an HTML receipt copy with VAT/TAX included at 13% and the LUX LIMO company stamp.
- Driver copy recipient resolution remains by driver identity from the Bolt driver directory, not vehicle plate.
- Live EDXEIX submission remains blocked.
- No submission_jobs or submission_attempts should be created by v5.1.

Important files:

- `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/mail-driver-notifications.php`
- `/home/cabnet/public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg`
- `/home/cabnet/gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_receipt_columns.sql`

Verification:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-driver-notifications.php
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" -e "SELECT id,intake_id,notification_status,receipt_status,receipt_vat_rate,receipt_total_amount,receipt_vat_amount,receipt_sent_at FROM bolt_mail_driver_notifications ORDER BY id DESC LIMIT 10;"
mysql "$DB_NAME" -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs;"
mysql "$DB_NAME" -e "SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
```
