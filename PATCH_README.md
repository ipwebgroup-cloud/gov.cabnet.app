# gov.cabnet.app — Ops UI Shell Phase 17 Deployment Center

## Upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/deployment-center.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/deployment-center.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/deployment-center.php
```

Open:

```text
https://gov.cabnet.app/ops/deployment-center.php
```

## Production safety

This patch does not modify:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

No Bolt calls, EDXEIX calls, AADE calls, workflow writes, queue staging, or live submission behavior are added.
