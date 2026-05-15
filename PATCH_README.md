# Patch README — V3 Next Real-Mail Candidate Watch v3.1.5

## What changed

Adds a read-only V3 watcher for the next possible real pre-ride email queue row before it expires.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php
docs/V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_20260515.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php
```

Optional docs mirror:

```text
/home/cabnet/docs/V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_20260515.md
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "future_possible=".($j["summary"]["future_possible_real_rows"]??"?").PHP_EOL; echo "operator_candidates=".($j["summary"]["operator_review_candidates"]??"?").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

## Expected result

- PHP syntax clean.
- Public ops page redirects unauthenticated users to `/ops/login.php`.
- `ok=true` if DB and queue are readable and the live gate is safely closed.
- `live_risk=false`.
- `final_blocks=[]`.

## Git commit title

Add V3 next real-mail candidate watch

## Git commit description

Adds a read-only V3 watcher for the next possible real pre-ride email queue row before it expires.

The watcher highlights future possible-real rows, complete operator-review candidates, urgent candidates, missing required fields, last_error rows, and closed live-gate posture.

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database write, queue mutation, or filesystem write actions are performed.

Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
