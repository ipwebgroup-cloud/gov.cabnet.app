# Deployment Notes

## Upload layout

Upload files to the matching cPanel paths:

```text
public_html/gov.cabnet.app/*  -> /home/cabnet/public_html/gov.cabnet.app/
gov.cabnet.app_app/*         -> /home/cabnet/gov.cabnet.app_app/
gov.cabnet.app_config/*      -> /home/cabnet/gov.cabnet.app_config/
gov.cabnet.app_sql/*         -> /home/cabnet/gov.cabnet.app_sql/
```

## Config

Real config files are not included. On the server:

```bash
cd /home/cabnet/gov.cabnet.app_config
cp config.php.example config.php
cp bolt.php.example bolt.php
```

Then fill values directly on the server.

## Smoke checks

```text
https://gov.cabnet.app/bolt_readiness_audit.php
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/bolt_sync_reference.php?dry_run=1
https://gov.cabnet.app/bolt_sync_orders.php?dry_run=1&hours_back=24
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
```

Do not run staging/job-creation endpoints unless you intend to create local queue records.
