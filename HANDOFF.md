# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.9:
- Gmail forwarding to `bolt-bridge@gov.cabnet.app` is working.
- Maildir intake cron runs every minute.
- Bolt Ride details emails are parsed into `bolt_mail_intake`.
- `future_start_guard_minutes` is now set to 2 for near-real-time production timing.
- Live EDXEIX submission remains disabled.
- v3.9 adds stale candidate expiry to the mail intake cron.
- Old `future_candidate`, `blocked_too_soon`, or open `needs_review` rows with pickup time in the past are automatically converted to `blocked_past` if they are not linked to a normalized booking.
- This prevents stale future candidates from being manually approved after pickup time passes.

Primary safe pages:
- `https://gov.cabnet.app/ops/mail-status.php?key=...`
- `https://gov.cabnet.app/ops/mail-intake.php?key=...`
- `https://gov.cabnet.app/ops/mail-preflight.php?key=...`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight and EDXEIX session/form access remains confirmed.
