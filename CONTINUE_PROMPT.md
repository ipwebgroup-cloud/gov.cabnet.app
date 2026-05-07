Continue gov.cabnet.app Bolt → EDXEIX bridge from v4.4.

Project constraints:
- Plain PHP, mysqli/MariaDB, cPanel/manual upload.
- No frameworks, Composer, Node, or heavy dependencies.
- Live EDXEIX submit must remain OFF unless Andreas explicitly approves live-submit work.
- Do not create `submission_jobs` or `submission_attempts` unless Andreas explicitly asks for a live-submit patch.

Current state:
- Mail intake is working via `bolt-bridge@gov.cabnet.app` Maildir.
- Cron imports mail every minute.
- Auto dry-run evidence worker runs every minute.
- Future guard is 2 minutes.
- Stale candidates expire automatically.
- Synthetic test harness works.
- Manual Mail Preflight local booking creation works.
- Auto dry-run can create local `source='bolt_mail'` bookings and dry-run evidence only.
- v4.4 added clearer production monitor dashboards, dry-run evidence detail pages, raw preflight guard alignment, and synthetic-only cleanup controls.

Important URLs:
- Mail Status: `https://gov.cabnet.app/ops/mail-status.php?key=INTERNAL_API_KEY`
- Auto Dry-run: `https://gov.cabnet.app/ops/mail-auto-dry-run.php?key=INTERNAL_API_KEY`
- Dry-run Evidence: `https://gov.cabnet.app/ops/mail-dry-run-evidence.php?key=INTERNAL_API_KEY`
- Mail Preflight: `https://gov.cabnet.app/ops/mail-preflight.php?key=INTERNAL_API_KEY`
- Raw Preflight JSON: `https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30`

Next action:
- Monitor the next real future Bolt email.
- Confirm import and auto dry-run evidence creation.
- Confirm no `submission_jobs`, no `submission_attempts`, and no live EDXEIX POST.
- Keep live submit disabled until explicit approval.
