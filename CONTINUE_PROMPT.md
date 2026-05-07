Continue the gov.cabnet.app Bolt → EDXEIX / AADE bridge from v5.8.1.

Important current behavior:

- Driver pre-ride copy sends automatically on new Bolt pre-ride emails.
- Automatic AADE receipt issuance is enabled for eligible new real Bolt mail bookings after `auto_issue_not_before`.
- v5.8.1 delays AADE SendInvoices and official driver receipt email until the booking pick-up time.
- For bolt_mail bookings, use `normalized_bookings.started_at` as the parsed pick-up time.
- Do not use the earlier Bolt email Start time as the receipt trigger.
- EDXEIX remains blocked/session-disconnected and no submission_jobs/submission_attempts should be created.

Next monitoring commands:

```bash
tail -f /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log
tail -f /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_auto_dry_run.log
```
