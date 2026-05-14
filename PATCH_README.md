# v3.0.45-v3-ops-home-integration

## What changed

This patch updates the V3 Control Center so it clearly points to the verified V3 visibility pages:

- Compact Monitor
- Queue Focus
- Pulse Focus
- Readiness Focus
- Storage Check
- Locked Submit Gate

It also updates the shared Ops navigation and continuity docs.

## Files included

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
docs/V3_OPS_HOME_INTEGRATION.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
```

Keep docs in the local GitHub Desktop repo unless intentionally publishing docs.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
```

## Expected result

- V3 Control Center loads.
- Verified focus pages are clearly linked.
- V0 remains untouched.
- Live submit remains disabled.
- No SQL or DB writes are introduced.

## Safety

This is a UI/navigation patch only. It does not call Bolt, EDXEIX, or AADE. It does not modify V0, cron behavior, queue mutation logic, or schema.
