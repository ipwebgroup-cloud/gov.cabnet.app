# Phase 46 — Handoff Package Tools

## Upload

Upload:

```text
public_html/gov.cabnet.app/ops/handoff-package-tools.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-tools.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-tools.php
```

Open:

```text
https://gov.cabnet.app/ops/handoff-package-tools.php
```

Expected:

- login required
- admin-only page opens inside shared ops shell
- tool shortcuts display
- private package directory status displays
- recent package list displays if packages exist
- no package is built by opening the page
- production pre-ride tool remains unchanged
