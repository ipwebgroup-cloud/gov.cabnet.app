# gov.cabnet.app — Handoff Update 2026-05-15 v3.0.86

Current live audit direction: tidy/de-bloat the gov.cabnet.app Bolt → EDXEIX bridge without disturbing production workflows.

Latest patch prepared: v3.0.86 Public Utility Reference Cleanup Plan.

Purpose:
- Continue the no-delete/no-move audit of six guarded public-root utilities.
- Show dependency evidence by category.
- Plan reference cleanup before any relocation.

Safety:
- No route moved.
- No route deleted.
- No SQL.
- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No DB connection.
- No filesystem writes by the planner.
- Production `/ops/pre-ride-email-tool.php` untouched.

Current recommendation:
- Do not relocate public-root utilities yet.
- Clean documentation/operator references first.
- Later create /ops wrappers or private CLI equivalents.
- Keep public-root compatibility until references and access logs are quiet.
