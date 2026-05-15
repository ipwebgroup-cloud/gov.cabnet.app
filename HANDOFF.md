# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-15  
Milestone: v3.0.83 public utility relocation plan prepared

## Current safety posture

- Live EDXEIX submission remains disabled.
- V3 live adapter remains skeleton-only/non-live.
- Production `/ops/pre-ride-email-tool.php` remains untouched.
- Public route exposure audit is installed and reports no final blocks.
- Public utility relocation planner is read-only and does not move/delete routes.

## Latest patch

v3.0.83 adds:

```text
/home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php
/home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php
```

It also links the planner from the Developer Archive and Route Index.

## Next safest step

After upload, run the dependency search commands shown by the planner. Do not relocate public-root utilities until cron/monitor/bookmark usage is known.
