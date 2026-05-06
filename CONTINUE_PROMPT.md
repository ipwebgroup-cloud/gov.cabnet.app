Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- Live EDXEIX submit remains disabled.
- Gmail forwarding to `bolt-bridge@gov.cabnet.app` is working.
- Cron imports Bolt pre-ride emails every 1 minute.
- `future_start_guard_minutes` is 2 for near-real-time intake.
- Stale future candidates are automatically expired.
- Mail Status, Mail Intake, Mail Preflight, and Synthetic Test are installed.
- v4.1 updated `/ops/mail-status.php` to show active unlinked candidates, linked local bookings, synthetic rows, stale open rows, and submission job/attempt safety counts.
- Synthetic test flow has successfully proven mail intake, future-candidate classification, mapping preview, local normalized booking creation, no submission job creation, and cleanup.

Next safe step:
- Use Synthetic Test or a real future Bolt email to create a current `future_candidate`.
- Preview it in Mail Preflight.
- Create one local normalized preflight booking only if needed.
- Confirm no `submission_jobs` and no `submission_attempts` are created.

Do not enable live EDXEIX submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
