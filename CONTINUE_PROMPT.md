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
- Bolt API Visibility Diagnostic v1.1 added local normalized booking summaries to explain dry-run cases where `orders_seen > 0` but sanitized samples are unavailable.
- Server screenshots showed the diagnostic works and records private sanitized timeline entries.
- Observed output at that time: `orders_seen: 1`, `sanitized_samples: 0`, watch matches `NO`.

Most recent patch:
- Bolt Dev Accelerator v1.2.
- Adds `/ops/dev-accelerator.php`.
- The page consolidates readiness status, fast dry-run capture buttons, auto-watch link, JSON output, known mapping reminders, real future candidate status, and copy/paste URLs.
- It does not enable live EDXEIX submission.
- It does not stage jobs.
- It does not edit mappings.
- It does not call Bolt on default page load.
- Optional capture buttons call the existing Bolt visibility diagnostic dry-run path only.

Next safest task:
- Upload the v1.2 patch.
- Open `/ops/dev-accelerator.php`.
- During a real future/scheduled Bolt ride with Filippos + EMX6874, capture the accepted/assigned, pickup/waiting, trip started, and completed states.
- Compare the accelerator, Bolt visibility diagnostic, future-test checklist, and preflight JSON.
- Do not submit live to EDXEIX.
