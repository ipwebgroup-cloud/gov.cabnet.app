# Ops UI Shell Phase 6 — Activity Center and Profile Activity

This patch continues the unified `/ops` GUI work.

It adds:

- `/ops/activity-center.php` — admin-only read-only summary for users, audit events, and login attempts.
- `/ops/profile-activity.php` — read-only account activity page for the logged-in operator.
- `/ops/_shell.php` v1.5 navigation additions.
- `/assets/css/gov-ops-shell.css` v1.5 activity/profile UI helpers.

Safety:

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not stage jobs.
- Does not write workflow data.
- Does not enable live EDXEIX submission.

SQL: none.
