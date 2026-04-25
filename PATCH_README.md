# Patch: Add ops access guard

## Purpose

Adds a lightweight access guard for `/ops/*.php` and public `bolt_*.php` diagnostic/worker endpoints.

## Files included

```text
.gitignore
gov.cabnet.app_app/lib/ops_guard.php
public_html/gov.cabnet.app/.user.ini
gov.cabnet.app_config_examples/ops.example.php
docs/OPS_ACCESS_GUARD.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/lib/ops_guard.php
→ /home/cabnet/gov.cabnet.app_app/lib/ops_guard.php

public_html/gov.cabnet.app/.user.ini
→ /home/cabnet/public_html/gov.cabnet.app/.user.ini

gov.cabnet.app_config_examples/ops.example.php
→ /home/cabnet/gov.cabnet.app_config_examples/ops.example.php
```

## Server-only config

Create the real config manually:

```bash
mkdir -p /home/cabnet/gov.cabnet.app_config /home/cabnet/gov.cabnet.app_config_examples
cp /home/cabnet/gov.cabnet.app_config_examples/ops.example.php /home/cabnet/gov.cabnet.app_config/ops.php
chmod 640 /home/cabnet/gov.cabnet.app_config/ops.php
nano /home/cabnet/gov.cabnet.app_config/ops.php
```

Do not commit `/home/cabnet/gov.cabnet.app_config/ops.php`.

## Verification

After enabling config, test from an allowed browser:

```text
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/bolt_readiness_audit.php
```

Expected: allowed users load pages; denied users receive HTTP 403.

## Safety

No Bolt request, EDXEIX request, database write, or live submission is added by this patch.
