# Phase 42 — Safe Handoff Package Inspector

## Files included

- `public_html/gov.cabnet.app/ops/handoff-package-inspector.php`
- `docs/OPS_UI_SHELL_PHASE42_HANDOFF_PACKAGE_INSPECTOR_2026_05_12.md`

## Upload path

`public_html/gov.cabnet.app/ops/handoff-package-inspector.php`
→ `/home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-inspector.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-inspector.php
```

Open:

`https://gov.cabnet.app/ops/handoff-package-inspector.php`

## Safety

Read-only inspector only. It does not build a ZIP, does not dump the database, does not read/display secrets, and does not modify the production pre-ride tool.
