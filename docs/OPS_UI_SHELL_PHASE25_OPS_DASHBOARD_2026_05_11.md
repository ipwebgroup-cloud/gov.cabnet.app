# Ops UI Shell Phase 25 — Ops Dashboard

Adds `/ops/ops-dashboard.php`, a read-only operations overview page inside the shared `/ops` shell.

## Safety contract

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not write database rows.
- Does not stage jobs.
- Does not enable live submission.
- Reads only safe local file status and safe DB counts.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/ops-dashboard.php
```

Open:

```text
https://gov.cabnet.app/ops/ops-dashboard.php
```
