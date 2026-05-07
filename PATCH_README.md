# gov.cabnet.app v5.0 — Guarded Live Submit Armed / Session Disconnected

## What changed

This patch prepares the controlled live-submit path while preserving Andreas' safety net: the EDXEIX session is explicitly disconnected.

The system can be armed with `live_submit_enabled=true` and `http_submit_enabled=true`, but live POST remains blocked until:

- `edxeix_session_connected=true`
- a valid EDXEIX session cookie and CSRF exist
- a one-shot booking lock is set
- the booking is a real future Bolt booking
- mappings, future guard, duplicate checks, and confirmation phrase pass

## Files included

```text
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php
gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php
gov.cabnet.app_app/cli/live_submit_one_booking.php
public_html/gov.cabnet.app/ops/live-submit-readiness.php
gov.cabnet.app_config_examples/live_submit.example.php
docs/BOLT_LIVE_SUBMIT_ARMED_V5_0.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
→ /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php

gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php
→ /home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php

gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php
→ /home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php

gov.cabnet.app_app/cli/live_submit_one_booking.php
→ /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php

public_html/gov.cabnet.app/ops/live-submit-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/live-submit-readiness.php
```

## SQL

No new SQL is required for this patch. The optional audit table is `edxeix_live_submission_audit`, already part of the project baseline. If it is missing, install:

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_04_25_live_submission_audit.sql
```

## Verify syntax

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
php -l /home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php
php -l /home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php
php -l /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/live-submit-readiness.php
```

## Arm live mode with session disconnected

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php --by=Andreas
```

Expected readiness verdict:

```text
LIVE_ARMED_SESSION_DISCONNECTED
```

## Safety

This patch does not create a live cron. It does not automatically submit anything. It does not call Bolt or EDXEIX during install or arming. It does not create submission jobs or attempts.
