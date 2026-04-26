Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project from this baseline.

Do not enable live EDXEIX submission.

Project:
- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow

Current state:
- Pre-live blocked baseline.
- Latest readiness observed: READY_FOR_REAL_BOLT_FUTURE_TEST.
- Real future candidates observed: 0.
- Live EDXEIX submit remains disabled.
- v1.1 Bolt API Visibility Diagnostic exists.
- v1.2 Bolt Dev Accelerator exists.
- v1.3 Bolt Evidence Bundle exists.
- v1.4 Bolt Evidence Report Export exists.
- v1.5 Bolt Test Session Control exists.
- v1.6 Bolt Preflight Review Assistant exists at `/ops/preflight-review.php`.

Next safest task:
- Wait for a real future Bolt ride and use `/ops/test-session.php`.
- Capture the ride stages.
- Export `/ops/evidence-report.php?format=md`.
- Review `/ops/preflight-review.php`.
- Do not submit live to EDXEIX.
