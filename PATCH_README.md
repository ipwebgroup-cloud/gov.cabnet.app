# gov.cabnet.app Patch — V3 Real-Mail Queue Health v3.1.0

## What changed

Adds a read-only V3 real-mail intake and queue health audit.

## Files included

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php`
- `docs/V3_REAL_MAIL_QUEUE_HEALTH_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php`

Optional docs mirror:

- `/home/cabnet/docs/V3_REAL_MAIL_QUEUE_HEALTH_20260515.md`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "possible_real=".($j["summary"]["possible_real_mail_recent_count"]??"?").PHP_EOL; echo "canary=".($j["summary"]["canary_recent_count"]??"?").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

Expected:

- PHP syntax checks pass.
- Ops route redirects unauthenticated users to `/ops/login.php`.
- CLI returns JSON.
- `live_risk=false` while closed-gate posture remains intact.

## Commit title

Add V3 real-mail queue health audit

## Commit description

Adds a read-only V3 real-mail intake and queue health audit for the closed-gate pre-ride email automation workflow.

The audit distinguishes generated canary rows from possible real-mail queue rows, reports future active rows, ready rows, stale locks, missing required fields, latest queue classifications, and live gate closed-posture status.

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database write, queue mutation, or filesystem write actions are performed.

Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
