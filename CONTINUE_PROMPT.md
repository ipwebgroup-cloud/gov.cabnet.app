Greetings Sophion. Continue the gov.cabnet.app Bolt → EDXEIX bridge from v4.7 Production Hardening.

Current state:

- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.
- Live EDXEIX submit must remain OFF unless explicitly requested.
- Mail intake cron is active.
- Auto dry-run cron is active.
- Bolt driver directory sync cron is active.
- Driver email copy is validated with a real Bolt pre-ride email.
- Driver notification routing is by Bolt driver identity/name/identifier, not by vehicle plate.
- v4.5.3 changed only the driver-facing email copy: estimated end time = estimated pickup + 30 minutes, and estimated price uses first value only.
- v4.7 added `/ops/launch-readiness.php?key=INTERNAL_API_KEY` as a read-only launch control panel.
- submission_jobs and submission_attempts must remain 0 during hardening.

Next safest direction:

1. Review the v4.7 launch control panel output.
2. Confirm cron health is green.
3. Confirm driver identity email coverage remains good.
4. Rotate exposed credentials and ops key before any live-submit design.
5. Do not implement live submit unless explicitly requested.

If a patch is needed, inspect current files first and provide changed/added files only.
