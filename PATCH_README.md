# gov.cabnet.app patch — Ops UI Shell Phase 18 Handoff Center

Upload paths:

- `public_html/gov.cabnet.app/ops/_shell.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/handoff-center.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php`

No SQL is required.

Verify:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
```

Open:

- `https://gov.cabnet.app/ops/handoff-center.php`
- `https://gov.cabnet.app/ops/handoff-center.php?format=text`

Production pre-ride tool is not modified.
