# gov.cabnet.app Patch — Phase 40 Mapping Exception Queue

## Files included

```text
public_html/gov.cabnet.app/ops/_mapping_nav.php
public_html/gov.cabnet.app/ops/mapping-exceptions.php
docs/OPS_UI_SHELL_PHASE40_MAPPING_EXCEPTION_QUEUE_2026_05_12.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/_mapping_nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_mapping_nav.php

public_html/gov.cabnet.app/ops/mapping-exceptions.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mapping-exceptions.php
```

## SQL to run

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_mapping_nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-exceptions.php
```

Open:

```text
https://gov.cabnet.app/ops/mapping-exceptions.php
```

Expected:

- login required
- page opens in shared ops shell
- exception queue displays
- WHITEBLUE / 1756 is treated as critical if starting point 612164 is not active
- production pre-ride tool remains unchanged
