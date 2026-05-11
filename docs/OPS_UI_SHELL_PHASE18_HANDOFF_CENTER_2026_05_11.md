# Ops UI Shell Phase 18 — Handoff Center

Adds `/ops/handoff-center.php`, a read-only shared-shell continuity page that generates a copy/paste prompt for starting a new Sophion session.

Safety posture:
- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not read or display secrets.
- Does not call Bolt, EDXEIX, AADE, or external services.
- Does not write database rows or stage jobs.

The page includes a safe file presence check and a plain-text view at `/ops/handoff-center.php?format=text`.
