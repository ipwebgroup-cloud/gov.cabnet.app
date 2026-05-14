# Patch v3.0.43 — V3 Pulse Focus

## What changed

Adds a V3-only read-only pulse focus page:

```text
/ops/pre-ride-email-v3-pulse-focus.php
```

The page shows pulse cron health, latest pulse summary, latest cron start/finish, last exit code, pulse lock owner/perms, recent errors, recent pulse events, and raw recent log tail.

It also updates the shared Ops sidebar navigation to include `V3 Pulse Focus`.

## Files included

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php
docs/V3_PULSE_FOCUS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php
```

Keep docs in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

## Expected result

After login, open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php
```

Expected posture:

```text
Pulse health: OK
Last exit code: 0
Pulse lock: OK
Owner/group: cabnet:cabnet
V0 untouched
V3 only
Live submit disabled
```

## Safety

No V0 helper files, live-submit behavior, EDXEIX calls, AADE behavior, queue mutation logic, or SQL schema are changed.
