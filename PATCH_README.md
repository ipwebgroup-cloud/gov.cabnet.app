# Patch README — v3.2.0 Real Future Candidate Capture Readiness

## What changed

Adds a read-only V3 Real Future Candidate Capture Readiness tool.

It detects whether a new possible-real future pre-ride queue row exists and displays:

- minutes until pickup,
- completeness,
- missing fields,
- parser/mapping/future-safety posture,
- closed-gate operator review qualification,
- urgency/about-to-expire posture,
- whether an operator alert would be appropriate.

It does **not** submit to EDXEIX and does **not** mutate the queue.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/_ops-nav.php
docs/V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_20260515.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Exact upload paths

Upload each file to the matching live path:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
/home/cabnet/docs/V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_20260515.md
```

For local repo continuity, also keep these at repo root:

```text
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.0\|real-future-candidate-capture-readiness\|Future Candidate Capture" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Expected result

Lint:

```text
No syntax errors detected
```

CLI JSON should include:

```text
ok=true
version=v3.2.0-v3-real-future-candidate-capture-readiness
live_risk_detected=false
live_submit_recommended_now=0
db_write_made=false
queue_mutation_made=false
bolt_call_made=false
edxeix_call_made=false
aade_call_made=false
final_blocks=[]
```

Unauthenticated web route:

```text
HTTP 302 to /ops/login.php?next=%2Fops%2Fpre-ride-email-v3-real-future-candidate-capture-readiness.php
```

Authenticated web page:

```text
V3 Real Future Candidate Capture Readiness page renders with safety banner, v3.2.0 badge, metrics, and candidate/missing-field tables.
```

## Git commit title

```text
Add V3 real future candidate capture readiness
```

## Git commit description

```text
- Add read-only CLI and Ops page for real future candidate capture readiness.
- Show minutes until pickup, completeness, missing fields, closed-gate review status, urgency, and operator-alert suitability.
- Add shared navigation entries for the new V3 capture readiness page.
- Add v3.2.0 handoff, continue prompt, and documentation.
- Keep production Pre-Ride Tool untouched and live EDXEIX submission disabled.
- No SQL changes, DB writes, queue mutations, Bolt calls, EDXEIX calls, or AADE calls.
```

## Safety

Production Pre-Ride Tool remains untouched:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

Live EDXEIX submission remains disabled. This patch only observes current queue/config state.
