# Patch README — v3.1.4 V3 Real-Mail Expiry Audit Alignment

## What changed

Updates the read-only V3 Real-Mail Expiry Reason Audit so queue-health possible-real counts are aligned with expiry-audit classification counts.

## Files included

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`
- `docs/V3_REAL_MAIL_EXPIRY_AUDIT_ALIGNMENT_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

Upload:

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php`
  to `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php`

- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`
  to `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`

Optional docs mirror:

- `docs/V3_REAL_MAIL_EXPIRY_AUDIT_ALIGNMENT_20260515.md`
  to `/home/cabnet/docs/V3_REAL_MAIL_EXPIRY_AUDIT_ALIGNMENT_20260515.md`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "possible_real=".($j["summary"]["possible_real_mail_rows"]??"?").PHP_EOL; echo "possible_real_expired=".($j["summary"]["possible_real_mail_expired_guard_rows"]??"?").PHP_EOL; echo "possible_real_non_expired=".($j["summary"]["possible_real_mail_non_expired_guard_rows"]??"?").PHP_EOL; echo "mapping_correction=".($j["summary"]["possible_real_mail_mapping_correction_rows"]??"?").PHP_EOL; echo "mismatch_explained=".(($j["summary"]["queue_health_vs_expiry_count_mismatch_explained"]??false)?"true":"false").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

## Expected result

- Syntax passes.
- Ops page returns HTTP 302 to `/ops/login.php` when unauthenticated.
- `ok=true`.
- `version=v3.1.4-v3-real-mail-expiry-audit-alignment`.
- `live_risk=false`.
- `final_blocks=[]`.

## Commit title

Align V3 expiry audit possible-real counts

## Commit description

Updates the read-only V3 Real-Mail Expiry Reason Audit to align queue-health possible-real counts with expiry-audit classification counts.

Adds summary fields for possible-real rows, canary rows, possible-real non-expired-guard rows, mapping-correction rows, other blocked rows, classification counts, and a safe mismatch explanation.

This explains why queue health may report more possible-real rows than the expired-guard total when a historical possible-real row is blocked for a non-expiry reason.

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database write, queue mutation, or filesystem write actions are performed.

Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
