# v3.0.44 — V3 Readiness Focus

## What changed

Adds a read-only V3 readiness focus page:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
```

It summarizes pulse health, queue status, newest queue row, mapping facts, recent error reasons, and the locked live-submit gate state.

## Files included

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
docs/V3_READINESS_FOCUS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
```

Docs should be kept in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
```

## Expected result

- Page loads after Ops login.
- Pulse health shows OK if cron remains healthy.
- V3 queue and mapping facts are visible.
- Live submit remains disabled.
- V0 remains untouched.

## Safety

- No V0 changes.
- No SQL.
- No DB writes.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No live-submit enablement.
