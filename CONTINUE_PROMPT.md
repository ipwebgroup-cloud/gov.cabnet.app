Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- Live EDXEIX submit remains disabled.
- Gmail forwarding to `bolt-bridge@gov.cabnet.app` is working.
- Cron imports Bolt pre-ride emails every 1 minute.
- `future_start_guard_minutes` is 2 for near-real-time intake.
- Stale future candidates are automatically expired.
- Mail Status and Mail Preflight are installed.
- v4.0 adds the synthetic mail test harness at `/ops/mail-synthetic-test.php?key=INTERNAL_KEY` and CLI `/home/cabnet/gov.cabnet.app_app/cli/create_synthetic_bolt_mail.php`.

Next safe step:
- Use the synthetic harness to create/import a future candidate.
- Preview it in Mail Preflight.
- Optionally create one local normalized preflight booking.
- Confirm no `submission_jobs` and no `submission_attempts` are created.

Do not enable live EDXEIX submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
