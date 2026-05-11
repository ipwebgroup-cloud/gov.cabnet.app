# Ops UI Shell Phase 2 — 2026-05-11

This patch continues the uniform `/ops` GUI work without modifying the production pre-ride email tool.

## Included changes

- Updates `/ops/_shell.php` to v1.1.
- Fixes sidebar profile markup so it no longer nests anchor tags.
- Adds user/profile navigation to the shared shell.
- Updates `/ops/home.php` to use the shared shell and user profile section.
- Adds `/ops/pre-ride-email-toolv2.php` as a safe development wrapper.
- Updates `/assets/css/gov-ops-shell.css` with embedded-tool and profile layout styles.

## Safety

- `/ops/pre-ride-email-tool.php` is not modified.
- No Bolt calls are added.
- No EDXEIX calls are added.
- No DB writes are added.
- Live EDXEIX submission remains blocked/operator-confirmed only.
