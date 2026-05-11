# gov.cabnet.app patch — Ops UI Shell Phase 11 Extension Pair Status

## Upload paths

Upload:

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/firefox-extensions-status.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/firefox-extensions-status.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/firefox-extensions-status.php
```

Open:

```text
https://gov.cabnet.app/ops/firefox-extensions-status.php
```

Expected:

- Login required.
- Page opens in the shared ops shell.
- It explains that both Firefox helpers remain required today.
- It shows detected helper folders, manifest versions/IDs, and safe file hashes.
- Production pre-ride tool remains unchanged.
