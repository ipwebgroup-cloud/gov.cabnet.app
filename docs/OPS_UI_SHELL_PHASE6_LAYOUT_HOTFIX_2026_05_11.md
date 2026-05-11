# Ops UI Shell Phase 6 Layout Hotfix — 2026-05-11

Fixes horizontal page drift/overflow seen on `/ops/profile-activity.php` and other activity/log pages.

## Scope

- CSS-only layout containment improvements plus `_shell.php` cache-busting query-string update.
- Wide tables remain scrollable inside their own cards.
- The whole page should no longer drift horizontally.
- Production pre-ride tool is not modified.

## Safety

This patch does not call Bolt, EDXEIX, or AADE. It does not write workflow data, stage jobs, or enable live submission.
