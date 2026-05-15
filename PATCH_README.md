# gov.cabnet.app patch — v3.0.89 Legacy Public Utility Ops Wrapper

## What changed

Adds a safe `/ops` wrapper/registry for legacy guarded public-root utility endpoints.

This patch does **not** move, delete, redirect, include, or execute the legacy utilities. It only creates a stable `/ops` destination that later cleanup patches can point to.

## Files included

```text
public_html/gov.cabnet.app/ops/legacy-public-utility.php
gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php
docs/LIVE_LEGACY_PUBLIC_UTILITY_OPS_WRAPPER_20260515.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload paths

```text
/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility.php
/home/cabnet/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php
```

Docs may be copied to repo `/docs/` and/or `/home/cabnet/docs/` as preferred.

## SQL

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility.php

grep -n "v3.0.89\|Legacy Public Utility Wrapper\|direct_execution_from_wrapper" \
  /home/cabnet/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility.php
```

Expected:

```text
No syntax errors
HTTP 302 to /ops/login.php when unauthenticated
v3.0.89 markers present
```

## Browser URL

```text
https://gov.cabnet.app/ops/legacy-public-utility.php
```

## Safety posture

- Production pre-ride tool untouched
- Public-root utilities untouched
- V3 live gate remains closed
- EDXEIX adapter remains skeleton/non-live
- No Bolt call
- No EDXEIX call
- No AADE call
- No DB connection
- No writes

## Commit title

Add legacy public utility ops wrapper

## Commit description

Adds a read-only `/ops` wrapper and registry for legacy guarded public-root Bolt/EDXEIX utility endpoints.

The wrapper provides a stable future target for reference cleanup after the Phase 2 preview showed actionable references remain and no safe cleanup candidates are currently available.

No routes are moved or deleted. Legacy public-root utility files are not redirected, included, or executed. No SQL changes are made. No Bolt, EDXEIX, AADE, database, or filesystem write actions are performed. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
