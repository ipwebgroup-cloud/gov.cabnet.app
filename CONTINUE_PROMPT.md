Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project from this baseline.

Do not enable live EDXEIX submission.

Project:
- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow

Source of truth order:
1. Latest uploaded files/screenshots/live outputs in the current chat
2. HANDOFF.md and CONTINUE_PROMPT.md
3. README.md, SCOPE.md, DEPLOYMENT.md, SECURITY.md, docs/, PROJECT_FILE_MANIFEST.md
4. GitHub repo
5. Prior memory only as background

Current state:
- Pre-live blocked baseline.
- Ops guard is active.
- EDXEIX session capture works.
- EDXEIX live submit remains disabled.
- Readiness currently shows `READY_FOR_REAL_BOLT_FUTURE_TEST`.
- Real future candidates are currently `0`.
- Dev Accelerator v1.2 works.
- Evidence Bundle v1.3 works and currently shows `WAITING_FOR_EVIDENCE` until real test snapshots are captured.
- Evidence Report Export v1.4 adds `/ops/evidence-report.php` for copy/paste Markdown and JSON evidence reports.

Important pages:
- `/ops/dev-accelerator.php`
- `/ops/evidence-bundle.php`
- `/ops/evidence-report.php`
- `/ops/bolt-api-visibility.php`
- `/ops/future-test.php`
- `/ops/readiness.php`
- `/bolt_edxeix_preflight.php?limit=30`

Next safest task:
- During a real future/scheduled Bolt ride with Filippos + EMX6874, use `/ops/dev-accelerator.php` to capture accepted/assigned, pickup/waiting, trip started, and completed stages.
- Then open `/ops/evidence-report.php` and paste the Markdown report into the chat.
- Do not submit live to EDXEIX.
