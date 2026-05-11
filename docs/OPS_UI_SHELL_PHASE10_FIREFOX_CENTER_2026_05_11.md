# Ops UI Shell Phase 10 — Firefox Helper Center — 2026-05-11

Adds a shared-shell Firefox Helper Center at `/ops/firefox-extension.php`.

## Safety contract

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not stage jobs.
- Does not write workflow data.
- Does not enable live EDXEIX submission.

## Behavior

- Shows current helper manifest version when available.
- Shows expected helper file presence, size, modified time, and SHA-256.
- Provides an authenticated ZIP download containing only approved helper files.
- Documents current temporary loading method and future signed XPI path.
