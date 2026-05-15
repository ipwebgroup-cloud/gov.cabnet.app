# PATCH README — v3.1.2 V3 Real-Mail Expiry Reason Audit

## Files included

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`
- `docs/V3_REAL_MAIL_EXPIRY_REASON_AUDIT_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

Upload:

`gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php`

to:

`/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php`

Upload:

`public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`

to:

`/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "expired_guard=".($j["summary"]["expired_by_future_safety_guard"]??"?").PHP_EOL; echo "possible_real_expired=".($j["summary"]["possible_real_mail_expired_guard_rows"]??"?").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

## Expected result

- No syntax errors
- HTTP 302 to `/ops/login.php` when unauthenticated
- `ok=true`
- `live_risk=false`
- `final_blocks=[]`
