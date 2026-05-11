# Ops UI Shell Phase 27 — Route Health Center

Adds `/ops/route-health.php`, a read-only route/file availability dashboard for selected `/ops` pages.

Safety:
- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not read/display secrets.
- Does not write database rows.
- Does not stage jobs or enable live submission.

Verification:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/route-health.php
```

Open:

```text
https://gov.cabnet.app/ops/route-health.php
```
