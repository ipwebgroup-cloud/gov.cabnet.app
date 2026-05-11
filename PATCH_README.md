# gov.cabnet.app patch — Ops UI Shell Phase 16 System Status

Upload files to the matching server paths. This patch is read-only and does not touch the production pre-ride tool.

## Upload paths

```text
public_html/gov.cabnet.app/ops/_shell.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

public_html/gov.cabnet.app/ops/system-status.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/system-status.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/system-status.php
```

Then open:

```text
https://gov.cabnet.app/ops/system-status.php
```
