# gov.cabnet.app — Ops UI Shell Phase 15 Tool Inventory

## What changed

Adds a read-only `/ops/tool-inventory.php` page inside the shared ops GUI. It lists selected operator routes and core support files, showing existence, modified time, size, and short SHA-256 fingerprints.

## Files included

```text
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/tool-inventory.php
docs/OPS_UI_SHELL_PHASE15_TOOL_INVENTORY_2026_05_11.md
PATCH_README.md
```

## Exact upload paths

```text
public_html/gov.cabnet.app/ops/_shell.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

public_html/gov.cabnet.app/ops/tool-inventory.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/tool-inventory.php
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/tool-inventory.php
```

## Verification URL

```text
https://gov.cabnet.app/ops/tool-inventory.php
```

## Expected result

- login required
- Tool Inventory opens inside shared ops shell
- production pre-ride tool remains unchanged
- route/file status is shown read-only

## Git commit title

Add ops tool inventory page

## Git commit description

Continues the unified EDXEIX-style /ops GUI by adding a read-only Tool Inventory page. The page lists selected operator routes and core support files with existence, modified time, size, and safe SHA-256 fingerprints.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, secret reads, workflow writes, queue staging, or live submission behavior are added.
