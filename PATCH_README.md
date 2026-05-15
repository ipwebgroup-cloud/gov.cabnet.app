# gov.cabnet.app patch — v3.2.2 Candidate Evidence Snapshot Export

## What changed

- Extended the existing v3.2.1 read-only capture readiness CLI to v3.2.2.
- Added sanitized `--evidence-json` / `--candidate-evidence-json` output.
- Added Candidate Evidence Snapshot section to the Ops readiness page.
- Updated shared shell/nav comments to v3.2.2.
- Preserved v3.2.1 `--watch-json`, `--snapshot-json`, and `--status-line` behavior.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/_ops-nav.php
docs/V3_CANDIDATE_EVIDENCE_SNAPSHOT_EXPORT_20260515.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
/home/cabnet/docs/V3_CANDIDATE_EVIDENCE_SNAPSHOT_EXPORT_20260515.md
```

Repo root:

```text
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

No SQL.

## Safety

- Production Pre-Ride Tool untouched.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No DB writes.
- No queue mutations.
- No filesystem writes from the tool.
- No cron installation.
- No notifications.
- No live-submit enablement.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --watch-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --evidence-json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.2\|evidence-json\|Candidate Evidence Snapshot\|payload_json\|unmasked_customer_phone" \
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Expected result

- Syntax passes.
- Watch JSON still works.
- Status line still works.
- Evidence JSON returns:

```text
snapshot_mode=read_only_sanitized_candidate_evidence_snapshot
```

- Candidate evidence snapshot includes safety confirmation:

```text
live_risk_detected=false
live_submit_recommended_now=0
db_write_made=false
queue_mutation_made=false
bolt_call_made=false
edxeix_call_made=false
aade_call_made=false
```

- Evidence snapshot hides raw payloads and unmasked customer phone.
- Web route remains protected by login and returns HTTP 302 when unauthenticated.

## Git commit title

```text
Add V3 candidate evidence snapshot export
```

## Git commit description

```text
- Add sanitized read-only candidate evidence snapshot output to the V3 real future candidate capture readiness CLI.
- Add --evidence-json / --candidate-evidence-json CLI modes for closed-gate operator review.
- Add Candidate Evidence Snapshot section to the Ops readiness page.
- Hide raw payloads, parsed JSON, hashes, full mailbox paths, raw headers, credentials, and unmasked customer phone numbers from the evidence export.
- Preserve watch-json/status-line behavior and keep live EDXEIX submission disabled.
- No SQL changes, DB writes, queue mutations, Bolt calls, EDXEIX calls, AADE calls, cron jobs, notifications, or live-submit enablement.
```
