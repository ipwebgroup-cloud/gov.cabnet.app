# Ops UI Shell Phase 48 — GUI Archive Package Builder

Adds admin-only browser-based archive building to `/ops/handoff-package-archive.php`.

## Scope

- Build persistent Safe Handoff ZIP packages directly from the GUI.
- Build with or without `DATABASE_EXPORT.sql`.
- Store packages outside the public webroot under `/home/cabnet/gov.cabnet.app_app/var/handoff-packages`.
- List, download, and delete whitelisted generated ZIP packages.
- Validate a newly built package when the validator class is available.

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not enable live submission.
- Does not copy real server-only config values.

Packages with `DATABASE_EXPORT.sql` are private operational material and must not be committed to GitHub unless intentionally sanitized.
