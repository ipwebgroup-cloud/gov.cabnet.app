# Bolt Mail Status Dashboard v3.8

Adds a read-only operations page:

`/ops/mail-status.php?key=YOUR_INTERNAL_API_KEY`

Purpose:
- Show total `bolt_mail_intake` rows.
- Show `future_candidate`, `blocked_past`, `blocked_too_soon`, needs-review, and mail-created booking counts.
- Show Maildir `new/` and `cur/` file counts.
- Show recent intake rows with masked passenger phone numbers.
- Show the latest cron log lines.

Safety contract:
- Does not scan the mailbox.
- Does not import email.
- Does not create normalized bookings.
- Does not create submission jobs.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not submit live.

Expected current state:
- Existing test rows remain `blocked_past`.
- `future_candidate` count remains `0` until a real future Bolt pre-ride email arrives.
- Cron log should show `OK files=... inserted=... duplicates=... errors=0`.

Verification:
```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php
```

Then open:
```text
https://gov.cabnet.app/ops/mail-status.php?key=YOUR_INTERNAL_API_KEY
```
