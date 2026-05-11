# Ops UI Shell Phase 12 — Mobile Compatibility — 2026-05-11

Adds a read-only shared-shell page for mobile compatibility guidance:

- `/ops/mobile-compatibility.php`

The page documents the current production rule:

- The production pre-ride email tool may be used on mobile for login/review/basic checking.
- The EDXEIX helper extension workflow remains desktop/laptop only today.
- Both current Firefox helpers should remain loaded on desktop Firefox.
- Mobile EDXEIX submission is not approved without a separate tested workflow.

No production workflow files are modified.

Production file intentionally untouched:

- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

No SQL is required.
