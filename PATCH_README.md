# Patch README — v3.0.63-v3-pre-live-switchboard

## What changed

Adds a V3-only read-only pre-live switchboard CLI and Ops page.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
docs/V3_PRE_LIVE_SWITCHBOARD.md
docs/V3_AUTOMATION_NEXT_STEPS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
```

Docs stay in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --json"
```

## Safety

No V0 changes. No live-submit enabling. No EDXEIX call. No AADE call. No queue mutation. No production submission table writes. No cron changes. No SQL schema changes.
