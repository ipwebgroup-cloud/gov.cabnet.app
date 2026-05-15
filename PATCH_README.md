# Patch README — v3.1.10 V3 Observation Overview Navigation

## What changed

Adds navigation links for the read-only V3 Real-Mail Observation Overview page.

The overview page was introduced in v3.1.9 and consolidates queue health, expiry audit, and next candidate watch status into one operator-safe board.

## Files included

```text
public_html/gov.cabnet.app/ops/_shell.php
docs/V3_OBSERVATION_OVERVIEW_NAV_20260515.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload path

Upload:

```text
public_html/gov.cabnet.app/ops/_shell.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Optional docs mirror:

```text
/home/cabnet/docs/V3_OBSERVATION_OVERVIEW_NAV_20260515.md
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-observation-overview.php

grep -n "v3.1.10\|V3 Observation Overview\|real-mail observation overview navigation" \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected:

```text
No syntax errors
HTTP 302 to /ops/login.php when unauthenticated
v3.1.10 marker present
V3 Observation Overview links present
```

## Safety

No route behavior changes. No database writes. No queue mutations. No Bolt, EDXEIX, or AADE calls. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
