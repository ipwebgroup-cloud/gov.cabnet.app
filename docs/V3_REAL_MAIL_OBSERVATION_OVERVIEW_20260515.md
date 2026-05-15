# V3 Real-Mail Observation Overview — 2026-05-15

## Version

`v3.1.9-v3-real-mail-observation-overview`

## Purpose

Adds a read-only consolidated V3 observation board for:

- V3 Real-Mail Queue Health
- V3 Real-Mail Expiry Reason Audit
- V3 Next Real-Mail Candidate Watch

The overview is intended as the daily operator-safe starting point for monitoring whether a future possible-real pre-ride email row exists before it expires.

## Safety posture

- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No database writes.
- No queue status changes.
- No filesystem writes from the CLI/page itself.
- No route moves.
- No route deletes.
- No redirects.
- Live EDXEIX submission remains disabled.
- Production `/ops/pre-ride-email-tool.php` remains untouched.

## Files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_observation_overview.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-observation-overview.php`

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_observation_overview.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-observation-overview.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-observation-overview.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_observation_overview.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "queue_ok=".(($j["summary"]["queue_health_ok"]??false)?"true":"false").PHP_EOL; echo "expiry_ok=".(($j["summary"]["expiry_audit_ok"]??false)?"true":"false").PHP_EOL; echo "watch_ok=".(($j["summary"]["candidate_watch_ok"]??false)?"true":"false").PHP_EOL; echo "future_active=".($j["summary"]["future_active_rows"]??"?").PHP_EOL; echo "operator_candidates=".($j["summary"]["operator_review_candidates"]??"?").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

Expected: syntax clean, unauthenticated HTTP 302 to login, `ok=true`, all components OK, `live_risk=false`, and `final_blocks=[]`.
