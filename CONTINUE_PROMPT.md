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
- Bolt API Visibility Diagnostic v1.1 works and records private sanitized timeline entries.
- Bolt Dev Accelerator v1.2 was uploaded, syntax-checked, and committed.
- Screenshots confirmed readiness is `READY_FOR_REAL_BOLT_FUTURE_TEST`.
- System is clean and waiting for a real future Bolt trip.
- Current visible counts: mapped drivers 1/2, mapped vehicles 2/15, real future candidates 0, local submission jobs 0, LAB rows/jobs 0, live attempts 0.

Most recent patch:
- Bolt Evidence Bundle v1.3.
- Adds `/ops/evidence-bundle.php`.
- Summarizes sanitized Bolt visibility snapshots, readiness state, stage coverage, watch matches, and a copy/paste recap.
- It is read-only and does not call Bolt, call EDXEIX, stage jobs, update mappings, write database rows, or enable live submission.

Next safest task:
- Upload and verify `/ops/evidence-bundle.php`.
- During a real future/scheduled Bolt ride with Filippos + EMX6874, use `/ops/dev-accelerator.php` to capture accepted/assigned, pickup/waiting, trip-started, and completed snapshots.
- Then open `/ops/evidence-bundle.php` to review evidence.
- Do not submit live to EDXEIX.
