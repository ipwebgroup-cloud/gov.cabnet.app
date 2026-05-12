# Patch: Phase 40 Mapping Exception Queue Hotfix

## Files included

```text
public_html/gov.cabnet.app/ops/mapping-exceptions.php
docs/OPS_UI_SHELL_PHASE40_MAPPING_EXCEPTION_QUEUE_HOTFIX_2026_05_12.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/mapping-exceptions.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mapping-exceptions.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-exceptions.php
```

Open:

```text
https://gov.cabnet.app/ops/mapping-exceptions.php
```

Expected: no 500; the exception queue loads or displays a safe diagnostic card.
