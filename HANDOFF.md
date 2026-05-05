# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.7:
- Gmail forwarding from `mykonoscab@gmail.com` to `bolt-bridge@gov.cabnet.app` is configured for Bolt Ride details emails.
- `bolt-bridge@gov.cabnet.app` mailbox receives forwarded Bolt emails.
- Maildir scanner and parser are deployed.
- Cron imports mail every 2 minutes into `bolt_mail_intake`.
- Duplicate protection is working.
- Historical/past ride emails are parsed but blocked as `blocked_past`.
- v3.7 adds a guarded Mail Intake → Preflight Candidate Bridge.
- `/ops/mail-preflight.php` can preview `future_candidate` rows and manually create local `normalized_bookings` rows for EDXEIX preflight review only.
- The bridge re-checks the future guard at approval time.
- The bridge requires driver/vehicle/start-point mapping before local booking creation.
- No submission jobs are created by this patch.
- Live EDXEIX submission remains disabled.

Primary safe entries:
- `https://gov.cabnet.app/ops/home.php`
- `https://gov.cabnet.app/ops/mail-intake.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-preflight.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30`

Critical safety rule:
Do not enable live EDXEIX submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes mail intake, local booking creation, mapping checks, and EDXEIX preflight review.

Credentials note:
The internal API key and DB password were visible during operator setup/testing. Rotate both before final live posture.
