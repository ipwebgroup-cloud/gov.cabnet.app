# HANDOFF — gov.cabnet.app V3 Live Audit State

Current state: v3.0.85 public utility dependency evidence planning.

The live production pre-ride tool remains untouched:

- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

Recent verified milestones:

- v3.0.75 live adapter contract test production-verified.
- v3.0.77/v3.0.78 Handoff Center package hygiene and DB audit option verified.
- v3.0.80 navigation de-bloat verified.
- v3.0.81/v3.0.82 public route exposure audit verified.
- v3.0.83/v3.0.84 public utility relocation planner and permission-safe scan verified.
- v3.0.85 dependency evidence classification prepared.

Safety posture remains unchanged:

- Live EDXEIX submission is disabled.
- EDXEIX adapter remains skeleton-only/non-live.
- V3 queue rows are blocked/expired where appropriate.
- No V3 submitted rows.
- No SQL changes in this patch.
- No route deletion or relocation in this patch.

Next safe step after v3.0.85 verification:

Prepare a no-break compatibility plan for the six public-root utility endpoints. Do not move them yet because ops/docs/code references still exist.
