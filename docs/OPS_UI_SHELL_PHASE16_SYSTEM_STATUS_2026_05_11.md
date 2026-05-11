# Ops UI Shell Phase 16 — System Status

Adds `/ops/system-status.php`, a read-only health snapshot page for the shared operations GUI.

## Safety contract

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not write database rows.
- Does not display secrets, passwords, API keys, tokens, cookies, or session values.

## Added / changed files

- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/system-status.php`

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/system-status.php
```

Open:

```text
https://gov.cabnet.app/ops/system-status.php
```
