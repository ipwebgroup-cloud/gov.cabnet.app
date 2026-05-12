# Phase 43 — Safe Handoff Package CLI Runner

## What changed

Adds:

```text
/home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php
/home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-cli.php
```

The CLI runner builds the Safe Handoff ZIP outside the browser request path and stores it in:

```text
/home/cabnet/gov.cabnet.app_app/var/handoff-packages
```

## Upload paths

```text
gov.cabnet.app_app/cli/build_safe_handoff_package.php
→ /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php

public_html/gov.cabnet.app/ops/handoff-package-cli.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-cli.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-cli.php
```

## URL

```text
https://gov.cabnet.app/ops/handoff-package-cli.php
```

## Production safety

No Bolt calls, no EDXEIX calls, no AADE calls, no workflow writes, no queue staging, no live submit behavior.
