# Ops UI Shell Phase 51 — EDXEIX Session Readiness

Adds `/ops/edxeix-session-readiness.php`, a read-only readiness page for the future mobile/server-side EDXEIX connector.

## Safety contract

- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not submit forms.
- Does not write database rows.
- Does not display cookies, sessions, passwords, CSRF token values, or real config values.
- Does not modify `/ops/pre-ride-email-tool.php`.

## Purpose

The page checks whether enough sanitized EDXEIX form/session metadata has been captured to design the server-side/mobile submit connector safely.

It reads the latest `ops_edxeix_submit_captures` row when available and displays a readiness checklist for:

- DB context availability
- sanitized capture availability
- form method
- action host/path
- required field names
- select/dropdown field names
- CSRF field name only
- coordinate/map field names
- submit behavior notes

Live submit remains disabled.
