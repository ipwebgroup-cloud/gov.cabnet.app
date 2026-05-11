# Ops UI Shell Phase 21 — Quick Launch

Adds `/ops/quick-launch.php`, a read-only route launcher for the shared operations GUI.

Safety:
- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt, EDXEIX, or AADE.
- Does not write database rows.
- Does not stage jobs.
- Does not enable live EDXEIX submission.

Purpose:
- Keep the top navigation compact.
- Provide a searchable route index for staff.
- Clarify production vs V2 development routes.
