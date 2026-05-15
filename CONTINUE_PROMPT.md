Continue the gov.cabnet.app Bolt → EDXEIX bridge project from v3.0.88.

The latest patch added a read-only Public Utility Reference Cleanup Phase 2 Preview. It scans remaining references to guarded public-root utility endpoints and separates actionable references from inventory/audit/planner references.

Critical rules:
- Do not enable live EDXEIX submission.
- Do not move or delete routes yet.
- Do not change SQL.
- Keep /ops/pre-ride-email-tool.php untouched unless Andreas explicitly requests a production hotfix.
- Use no-break cleanup: docs and ops/admin links first, compatibility wrappers later, route removal only after explicit approval and quiet-period evidence.
