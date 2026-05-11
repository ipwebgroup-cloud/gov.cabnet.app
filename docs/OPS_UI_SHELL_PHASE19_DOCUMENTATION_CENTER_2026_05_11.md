# Ops UI Shell Phase 19 — Documentation Center

Adds `/ops/documentation-center.php`, a read-only shared-shell index for operator guides, user/profile pages, admin visibility pages, helper guidance, deployment notes, and continuity tools.

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not read/display secrets.
- Does not write database rows.
- Does not stage jobs.
- Does not enable live EDXEIX submission.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/documentation-center.php
```

Open:

```text
https://gov.cabnet.app/ops/documentation-center.php
```
