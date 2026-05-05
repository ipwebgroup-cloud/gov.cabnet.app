# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.8:
- Live EDXEIX submission remains disabled.
- Gmail filtered forwarding from `mykonoscab@gmail.com` to `bolt-bridge@gov.cabnet.app` is working.
- `bolt-bridge@gov.cabnet.app` receives Bolt Ride details emails in Maildir.
- v3.5 added `bolt_mail_intake` and parsed forwarded Bolt Ride details emails.
- v3.6 added private CLI cron scanner `gov.cabnet.app_app/cli/import_bolt_mail.php`.
- Cron runs every 2 minutes and logs to `/home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log`.
- v3.7 added guarded Mail Intake → Preflight Candidate Bridge at `/ops/mail-preflight.php`.
- v3.8 adds read-only Mail Status dashboard at `/ops/mail-status.php`.

Confirmed current behavior:
- Two forwarded test emails parsed successfully.
- Both are `blocked_past`.
- Duplicate protection works.
- Cron repeats with errors=0.
- No normalized bookings from mail intake exist yet because no valid `future_candidate` exists.

Primary safe entries:
- `https://gov.cabnet.app/ops/mail-status.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-intake.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-preflight.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/home.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes mail intake, mapping checks, preflight, and EDXEIX session/form readiness.
