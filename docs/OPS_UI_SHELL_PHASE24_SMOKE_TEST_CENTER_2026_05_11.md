# Ops UI Shell Phase 24 — Smoke Test Center — 2026-05-11

Adds `/ops/smoke-test-center.php`, a read-only post-deployment verification page.

Safety contract:
- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not write database rows.
- Does not stage jobs or enable live submission.
- Does not read/display real config secrets.

Purpose:
- Confirm core files exist after manual upload.
- Confirm DB ping and selected table presence/counts.
- Provide copy/paste syntax and auth checks.
- Link to safe maintenance/status pages.
