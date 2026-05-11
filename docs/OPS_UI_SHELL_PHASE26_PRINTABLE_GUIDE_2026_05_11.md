# Ops UI Shell Phase 26 — Printable Operator Guide

Adds `/ops/print-guide.php`, a read-only printable staff guide for the manual Bolt pre-ride email → EDXEIX workflow.

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
php -l /home/cabnet/public_html/gov.cabnet.app/ops/print-guide.php
```

Open:

```text
https://gov.cabnet.app/ops/print-guide.php
```
