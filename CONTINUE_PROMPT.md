Continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current milestone: v3.0.87 public utility reference cleanup phase 1.

The goal is live-site audit, tidy-up, and de-bloat without changing the production pre-ride tool or enabling live EDXEIX submission.

Production pre-ride tool remains untouched:
`/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

v3.0.87 updates selected legacy ops pages and docs to stop pointing operators directly at guarded public-root utility endpoints. It points them to `/ops/public-utility-relocation-plan.php` and `/ops/public-route-exposure-audit.php` instead. No endpoints were moved or deleted.

Continue with read-only/no-delete cleanup and rerun planner checks before any relocation.
