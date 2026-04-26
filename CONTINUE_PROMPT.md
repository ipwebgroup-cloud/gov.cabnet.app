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
- Live EDXEIX submit remains disabled.
- Readiness currently indicates `READY_FOR_REAL_BOLT_FUTURE_TEST`.
- Current blocker: no real future Bolt candidate yet.
- Bolt API Visibility Diagnostic v1.1 works.
- Bolt Dev Accelerator v1.2 works.
- Bolt Evidence Bundle v1.3 works and currently waits for evidence when no snapshots exist.
- Bolt Evidence Report Export v1.4 works.
- Bolt Test Session Control v1.5 adds `/ops/test-session.php` as the primary low-risk workflow launcher.

Important workflow:
1. Open `/ops/test-session.php`.
2. Confirm readiness.
3. During a real future Bolt ride, use the capture buttons to record accepted/assigned, pickup/waiting, trip-started, and completed evidence.
4. Open `/ops/evidence-bundle.php` to inspect evidence.
5. Open `/ops/evidence-report.php?format=md`, copy the report, and paste it into chat.
6. Review `/bolt_edxeix_preflight.php?limit=30` only after evidence exists.
7. Do not submit live.

Known mappings:
- Filippos Giannakopoulos: Bolt UUID `57256761-d21b-4940-a3ca-bdcec5ef6af1`, EDXEIX driver ID `17585`.
- EMX6874: EDXEIX vehicle ID `13799`.
- EHA2545: EDXEIX vehicle ID `5949`.
- Leave Georgios Zachariou unmapped until exact EDXEIX driver ID is confirmed.
