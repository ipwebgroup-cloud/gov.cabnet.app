# Patch: Add ops access guard

## Purpose

Adds a lightweight access guard for `/ops/*.php` and public `bolt_*.php` diagnostic/worker endpoints.

This corrected package also includes a server-only `ops.php` config already allowlisting Andreas' current public IP:

```text
2.87.234.195
```

## Files included

```text
.gitignore
gov.cabnet.app_app/lib/ops_guard.php
gov.cabnet.app_config/ops.php
gov.cabnet.app_config_examples/ops.example.php
public_html/gov.cabnet.app/.user.ini
docs/OPS_ACCESS_GUARD.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/lib/ops_guard.php
→ /home/cabnet/gov.cabnet.app_app/lib/ops_guard.php

gov.cabnet.app_config/ops.php
→ /home/cabnet/gov.cabnet.app_config/ops.php

gov.cabnet.app_config_examples/ops.example.php
→ /home/cabnet/gov.cabnet.app_config_examples/ops.example.php

public_html/gov.cabnet.app/.user.ini
→ /home/cabnet/public_html/gov.cabnet.app/.user.ini
```

## Ownership / permissions

If files are uploaded with cPanel File Manager as the `cabnet` account, ownership should be correct automatically.

If files are copied/unzipped as `root`, run:

```bash
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_config/ops.php
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_app/lib/ops_guard.php
chown cabnet:cabnet /home/cabnet/public_html/gov.cabnet.app/.user.ini
chown -R cabnet:cabnet /home/cabnet/gov.cabnet.app_config_examples
chmod 640 /home/cabnet/gov.cabnet.app_config/ops.php
chmod 644 /home/cabnet/gov.cabnet.app_app/lib/ops_guard.php
chmod 644 /home/cabnet/public_html/gov.cabnet.app/.user.ini
```

## GitHub commit note

Commit these files:

```text
.gitignore
gov.cabnet.app_app/lib/ops_guard.php
gov.cabnet.app_config_examples/ops.example.php
public_html/gov.cabnet.app/.user.ini
docs/OPS_ACCESS_GUARD.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

Do **not** commit the real server config:

```text
gov.cabnet.app_config/ops.php
```

It is intentionally ignored by `.gitignore`.

## Verification

After enabling config, test from the allowed browser/IP:

```text
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/bolt_readiness_audit.php
```

Expected: allowed users load pages; denied users receive HTTP 403.

If the guard does not activate immediately, wait a few minutes because PHP/cPanel may cache `.user.ini`.

## Safety

No Bolt request, EDXEIX request, database write, or live submission is added by this patch.
