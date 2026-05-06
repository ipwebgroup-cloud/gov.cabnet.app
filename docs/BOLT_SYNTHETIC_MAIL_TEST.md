# Bolt Synthetic Mail Test Harness

Version: v4.0

This patch adds a controlled synthetic Bolt `Ride details` email generator for `gov.cabnet.app`.

Purpose:
- Test the Maildir parser without a real rider-app transaction.
- Avoid credit-card charges during development.
- Validate near-real-time intake, future-candidate classification, mapping preview, and Mail Preflight.

Safety contract:
- Does not call Bolt.
- Does not call EDXEIX.
- Does not create submission jobs.
- Does not submit live.
- Uses the synthetic customer name `CABNET TEST DO NOT SUBMIT`.
- Synthetic rows can be manually closed as `blocked_past`.

Files:
- `gov.cabnet.app_app/src/Mail/BoltSyntheticMailFactory.php`
- `gov.cabnet.app_app/cli/create_synthetic_bolt_mail.php`
- `public_html/gov.cabnet.app/ops/mail-synthetic-test.php`

Browser URL:

```text
https://gov.cabnet.app/ops/mail-synthetic-test.php?key=YOUR_INTERNAL_API_KEY
```

CLI example:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/create_synthetic_bolt_mail.php --lead=15 --duration=30 --import-now
```

Expected result:
- A synthetic email file is created in `/home/cabnet/mail/gov.cabnet.app/bolt-bridge/new`.
- The importer can create a `bolt_mail_intake` row.
- With a future pickup, the row becomes `future_candidate`.
- Mail Preflight can preview the row.

Cleanup:
Use the browser button `Close unlinked synthetic rows`, or run:

```sql
UPDATE bolt_mail_intake
SET safety_status='blocked_past',
    rejection_reason='Synthetic CABNET test email closed manually; not a real Bolt ride.'
WHERE customer_name LIKE 'CABNET TEST%'
  AND linked_booking_id IS NULL;
```
