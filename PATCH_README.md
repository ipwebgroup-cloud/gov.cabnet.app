# gov.cabnet.app patch — v3.2.4 Expired Candidate Safety Regression Audit

## What changed

- Adds read-only expired candidate safety regression audit.
- Adds CLI modes:
  - `--expired-safety-json`
  - `--stale-ready-audit-json`
  - `--regression-audit-json`
- Adds an Expired Candidate Safety Regression Audit section to the Ops readiness page.
- Detects stale `live_submit_ready` rows whose pickup is no longer future-safe.
- Confirms stale ready rows are not eligible for closed-gate review, operator alert, or live submission.
- Cleans the remaining shared shell side-note typo.

## Files included

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/_ops-nav.php`
- `docs/V3_EXPIRED_CANDIDATE_SAFETY_REGRESSION_AUDIT_20260515.md`
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

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --watch-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --evidence-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --edxeix-preview-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --expired-safety-json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.4\|expired-safety-json\|Expired Candidate Safety\|stale_live_submit_ready\|sharedshell" /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected safe posture:

```text
snapshot_mode=read_only_expired_candidate_safety_regression_audit
live_risk_detected=false
live_submit_recommended_now=0
db_write_made=false
queue_mutation_made=false
bolt_call_made=false
edxeix_call_made=false
aade_call_made=false
```

After the demo candidate expires, expected audit outcome:

```text
audit_outcome=stale_live_ready_rows_safely_blocked
eligibility_regression_passed=true
```
