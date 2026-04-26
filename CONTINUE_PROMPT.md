Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project from this baseline.

Do not enable live EDXEIX submission.

Project:
- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow

Current state:
- Pre-live blocked baseline.
- Readiness is clean: READY_FOR_REAL_BOLT_FUTURE_TEST.
- Real future Bolt candidate count is 0.
- Historical/terminal Bolt rows are visible and correctly blocked.
- Live EDXEIX submit remains disabled.
- No EDXEIX HTTP submit is enabled.
- No job staging should happen unless explicitly requested as dry-run/local only.

Available ops pages:
- /ops/test-session.php
- /ops/preflight-review.php
- /ops/dev-accelerator.php
- /ops/evidence-bundle.php
- /ops/evidence-report.php
- /ops/readiness.php
- /ops/future-test.php
- /ops/mappings.php
- /ops/bolt-api-visibility.php

Most recent patch:
- v1.7 Bolt Ops UI Polish.
- Added /assets/css/gov-ops-edxeix.css.
- Applied the EDXEIX-style presentation layer to /ops/test-session.php and /ops/preflight-review.php only.
- Presentation-only. No logic changes.

Next safest task:
- If a real future Bolt ride is available, use /ops/test-session.php and capture accepted, pickup, started, and completed stages.
- If no real ride is available, continue GUI polish in the next small batch for /ops/dev-accelerator.php and /ops/evidence-bundle.php without changing logic.
