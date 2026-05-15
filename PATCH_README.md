# gov.cabnet.app Patch — v3.2.5 Controlled Live-Submit Readiness Checklist

## What changed

Adds a read-only controlled live-submit readiness checklist / go-no-go snapshot to the existing V3 real future candidate capture readiness toolchain.

## Files included

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/_ops-nav.php`
- `docs/V3_CONTROLLED_LIVE_SUBMIT_READINESS_CHECKLIST_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Safety

- Production Pre-Ride Tool untouched.
- No SQL changes.
- No DB writes.
- No queue mutations.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No cron jobs.
- No notifications.
- Live submit remains disabled.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --live-readiness-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --go-no-go-json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.5\|live-readiness-json\|Controlled Live-Submit Readiness\|live_submit_allowed_now" \
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```
