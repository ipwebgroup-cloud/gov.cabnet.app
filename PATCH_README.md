# Phase 48 — GUI Archive Package Builder

## Upload path

`public_html/gov.cabnet.app/ops/handoff-package-archive.php`
→ `/home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-archive.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-archive.php
```

Open:

```text
https://gov.cabnet.app/ops/handoff-package-archive.php
```

Expected:

- admin-only page opens in the shared shell
- Build archived ZIP with database button exists
- Build archived ZIP without database button exists
- generated package appears in archive list
- no production pre-ride workflow changes
