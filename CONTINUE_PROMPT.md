# CONTINUE PROMPT — gov.cabnet.app Bolt → EDXEIX Bridge

Continue assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge.

Current state:

- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.
- Live EDXEIX submission remains disabled.
- Production pre-ride tool `/ops/pre-ride-email-tool.php` must remain untouched unless Andreas explicitly requests a production hotfix.
- v3.0.83 public utility relocation planner has been prepared/installed as a read-only tool.
- The planner targets six guarded public-root utilities for future no-break relocation planning only.

Next safest action:

Run and review the dependency search commands from `/ops/public-utility-relocation-plan.php`. Do not move, delete, disable, or rewrite public-root utility routes until cron/monitor/bookmark usage is known.
