Continue the gov.cabnet.app Bolt → EDXEIX bridge from v4.5.3.

Project stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.

Current state:
- Mail intake and auto dry-run crons are active.
- Live EDXEIX submission is OFF.
- Driver pre-ride copy is enabled and validated with a real Bolt email.
- Driver emails are synced from Bolt driver directory and matched by driver identity/name, not vehicle plate.
- v4.5.3 adjusted only the driver-facing email body:
  - Estimated end time = estimated pick-up time + 30 minutes.
  - Estimated price range shows first value only, e.g. 40.00 eur.

Next safest work:
- Test one future Bolt ride far enough ahead to confirm driver copy + dry-run evidence + zero jobs/attempts.
- Keep live EDXEIX submission blocked unless Andreas explicitly requests a live-submit patch.
