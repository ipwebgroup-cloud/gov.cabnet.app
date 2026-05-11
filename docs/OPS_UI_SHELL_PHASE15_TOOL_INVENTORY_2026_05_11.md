# Ops UI Shell Phase 15 — Tool Inventory

Adds `/ops/tool-inventory.php`, a read-only shared-shell page that inventories selected operator routes and core support files.

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not write database rows.
- Does not read real server-only config files or secrets.

## Upload

- `public_html/gov.cabnet.app/ops/_shell.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/tool-inventory.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/tool-inventory.php`

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/tool-inventory.php
```

Open:

```text
https://gov.cabnet.app/ops/tool-inventory.php
```
