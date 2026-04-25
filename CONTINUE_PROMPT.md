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
- Bolt API Visibility Diagnostic v1.0 was uploaded and committed on 2026-04-25.
- Server screenshots showed the diagnostic works and records private sanitized timeline entries.
- Observed output: `orders_seen: 1`, `sanitized_samples: 0`, watch matches `NO`.

Most recent patch:
- Bolt API Visibility Diagnostic v1.1.
- Adds read-only local normalized booking summaries to explain cases where the dry-run sync reports orders but does not expose order-like arrays for sanitized samples.

Next safest task:
- During a real future/scheduled Bolt ride with Filippos + EMX6874, use `/ops/bolt-api-visibility.php` with recording enabled and auto-refresh.
- Capture accepted/assigned, pickup/waiting, trip started, and completed states.
- Do not submit live to EDXEIX.
