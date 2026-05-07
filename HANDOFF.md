# gov.cabnet.app Bolt → EDXEIX Bridge Handoff

Current state after v4.5.3:

- Mail intake cron is active and imports Bolt pre-ride emails.
- Auto dry-run cron is active and remains local/preflight-only.
- Live EDXEIX submission remains OFF.
- Driver notification feature is enabled and validated with a real Bolt pre-ride email.
- Driver recipient resolution uses Bolt driver directory identity/name, not vehicle plate.
- v4.5.3 changes only the driver-facing copy formatting:
  - Estimated end time shown to the driver is estimated pick-up time + 30 minutes.
  - Estimated price range shown to the driver keeps only the first price value.

Safety boundaries:

- No submission_jobs are created by the driver copy feature.
- No submission_attempts are created by the driver copy feature.
- No EDXEIX POST occurs.
- Do not enable live EDXEIX submission unless Andreas explicitly requests a live-submit patch.
