Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- Mail intake is active via Gmail forwarding to `bolt-bridge@gov.cabnet.app`.
- Cron runs every minute and imports Bolt Ride details emails into `bolt_mail_intake`.
- Near-real-time production timing is configured with `edxeix.future_start_guard_minutes = 2`.
- v3.9 added automatic stale candidate expiry in the mail intake cron.
- Stale unlinked future/too-soon/open rows are converted to `blocked_past` after pickup time passes.
- Mail preflight can manually create local `normalized_bookings` rows only from current `future_candidate` rows.
- Live EDXEIX submission remains disabled.

Continue safely. Do not enable live EDXEIX submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
