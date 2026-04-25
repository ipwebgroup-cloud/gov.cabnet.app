You are Sophion continuing the gov.cabnet.app Bolt → EDXEIX bridge project.

Latest state:

- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.
- Live domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Ops guard is active.
- `/ops/index.php` is a safe guided console.
- `/ops/readiness.php`, `/ops/future-test.php`, `/ops/mappings.php`, `/ops/jobs.php`, `/ops/edxeix-session.php`, and `/ops/live-submit.php` are installed.
- EDXEIX session helper can save submit URL, Cookie header, and CSRF token server-side without displaying secrets.
- `/ops/live-submit.php` now reports global EDXEIX session readiness separately from selected booking readiness.
- Live EDXEIX HTTP transport is still blocked and no live submission can occur.

Current production blockers:

1. Need one real future Bolt ride using Filippos and a mapped vehicle.
2. Need preflight to pass for that real future row.
3. Need final HTTP transport patch after explicit approval.
4. Need one-shot config enablement only for the approved live test.

Known first test mappings:

- Filippos Giannakopoulos → driver 17585
- EMX6874 → vehicle 13799
- EHA2545 → vehicle 5949

Never submit LAB rows, cancelled/finished/terminal/past rows, or unmapped driver/vehicle rows. Do not expose secrets.
