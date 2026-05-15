# V3 Next Real-Mail Candidate Watch — 2026-05-15

## Purpose

Adds a read-only watcher for the next possible real V3 pre-ride email queue row before it expires.

This is part of the closed-gate V3 pre-ride automation observation phase. It does not submit to EDXEIX and does not change any database rows.

## Version

`v3.1.5-v3-next-real-mail-candidate-watch`

## Files

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php`

## Safety posture

- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No DB writes.
- No queue status changes.
- No filesystem writes.
- No route moves/deletes/redirects.
- Live EDXEIX submission remains disabled.
- Production pre-ride tool remains untouched.

## What it reports

- Possible-real rows scanned.
- Canary rows scanned.
- Future possible-real rows.
- Complete future possible-real rows.
- Operator review candidates.
- Urgent operator review candidates.
- Rows missing required fields.
- Rows with last_error.
- Already failed/blocked or submitted future rows.
- Live gate expected-closed posture.
- Live risk detection.

## Operational meaning

If `operator_review_candidates` is `0`, there is no current real-mail row for V3 closed-gate inspection.

If `operator_review_candidates` becomes greater than `0`, inspect the row with existing V3 closed-gate tools before pickup expires. This does not approve live EDXEIX submission.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "future_possible=".($j["summary"]["future_possible_real_rows"]??"?").PHP_EOL; echo "operator_candidates=".($j["summary"]["operator_review_candidates"]??"?").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```
