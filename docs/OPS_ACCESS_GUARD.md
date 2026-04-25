# gov.cabnet.app — Ops Access Guard

This patch adds a lightweight access guard for operational pages and diagnostic JSON endpoints.

## Files

```text
gov.cabnet.app_app/lib/ops_guard.php
public_html/gov.cabnet.app/.user.ini
gov.cabnet.app_config_examples/ops.example.php
```

## What it protects

By default, when enabled in server-only config, the guard protects:

```text
/ops/*.php
/bolt_*.php
```

This includes readiness, jobs, local test booking, LAB cleanup, Bolt sync JSON, preflight JSON, queue JSON, staging, and the dry-run worker.

## Safety contract

- No framework, Composer, Node, or sessions.
- No database dependency.
- No live EDXEIX submission behavior.
- No secrets committed to GitHub.
- Real `/home/cabnet/gov.cabnet.app_config/ops.php` must stay server-only.

## Install

Upload:

```text
public_html/gov.cabnet.app/.user.ini
→ /home/cabnet/public_html/gov.cabnet.app/.user.ini

gov.cabnet.app_app/lib/ops_guard.php
→ /home/cabnet/gov.cabnet.app_app/lib/ops_guard.php

gov.cabnet.app_config_examples/ops.example.php
→ /home/cabnet/gov.cabnet.app_config_examples/ops.example.php
```

Then create the real server-only config:

```bash
mkdir -p /home/cabnet/gov.cabnet.app_config
cp /home/cabnet/gov.cabnet.app_config_examples/ops.example.php /home/cabnet/gov.cabnet.app_config/ops.php
chmod 640 /home/cabnet/gov.cabnet.app_config/ops.php
```

Edit:

```bash
nano /home/cabnet/gov.cabnet.app_config/ops.php
```

Add at least one safe access method:

1. IP allowlist in `allowed_ips`, or
2. token hash in `token_hash` plus `cookie_secret`.

## Generate token hash

```bash
php -r "echo password_hash('CHANGE_THIS_LONG_RANDOM_TOKEN', PASSWORD_DEFAULT), PHP_EOL;"
```

Put the output into `token_hash`.

Generate cookie secret:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Put the output into `cookie_secret`.

Then open once:

```text
https://gov.cabnet.app/ops/readiness.php?ops_token=CHANGE_THIS_LONG_RANDOM_TOKEN
```

A signed cookie will be set if cookie support is enabled.

## Verify

```text
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/bolt_readiness_audit.php
```

Expected after enabling config:

- Allowed IP or valid token: page loads.
- Other clients: HTTP 403 access denied.
- JSON endpoints return JSON 403.

## Notes

`.user.ini` can be cached by PHP for a few minutes. If the guard does not activate immediately, wait 5 minutes or restart/reload PHP-FPM from WHM if appropriate.

## Preconfigured IP-only config included in corrected patch

The corrected package includes:

```text
gov.cabnet.app_config/ops.php
```

Upload it to:

```text
/home/cabnet/gov.cabnet.app_config/ops.php
```

It is configured for IP-only access using:

```text
2.87.234.195
```

This real config file is server-only and must not be committed to GitHub.

If it was copied as `root`, repair ownership:

```bash
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_config/ops.php
chmod 640 /home/cabnet/gov.cabnet.app_config/ops.php
```
