# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

Current patch: v3.2.2 — Candidate Evidence Snapshot Export
Date: 2026-05-15

## Project identity

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Do not introduce frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.

## Production safety posture

- Production Pre-Ride Tool remains untouched:
  `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`
- V0 workflow untouched.
- Live EDXEIX submit disabled.
- V3 live gate closed.
- No Bolt calls from v3.2.2.
- No EDXEIX calls from v3.2.2.
- No AADE calls from v3.2.2.
- No DB writes from v3.2.2.
- No queue mutations from v3.2.2.
- No SQL changes.
- No route moves/deletes/redirects.
- No cron jobs or notifications.

## Latest verified milestone before v3.2.2

A real-format pre-ride email was manually placed into the Bolt bridge mailbox path and ingested by the V3 queue.

v3.2.1 status-line detected:

```text
action=REVIEW_COMPLETE_FUTURE_CANDIDATE | severity=urgent | future=1 | review=1 | alerts=1 | urgent=1 | live_risk=no | next_id=1457 | minutes=19 | complete=yes | priority=soon
```

Full report confirmed:

- queue id `1457`
- `queue_status=live_submit_ready`
- future possible-real row: 1
- complete future row: 1
- missing required fields: none
- closed-gate operator review candidate: 1
- operator alert appropriate: 1
- live gate expected closed: true
- live risk detected: false
- live submit recommended now: 0
- DB write made by observation: false
- queue mutation made by observation: false
- Bolt/EDXEIX/AADE calls made by observation: false

Interpretation: the real-format pre-ride email path is proven through parsing, mapping, queue insertion, and closed-gate readiness detection. Live EDXEIX submission remains disabled.

## v3.2.2 changes

- Adds sanitized candidate evidence snapshot export to the existing V3 capture readiness CLI.
- Adds `--evidence-json` / `--candidate-evidence-json` CLI output.
- Adds Candidate Evidence Snapshot section to the Ops readiness page.
- Hides raw payloads, parsed JSON, hashes, full source mailbox paths, raw message headers, credentials, and unmasked customer phone numbers.
- Updates shared shell text to v3.2.2.

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
- Evidence JSON returns `snapshot_mode=read_only_sanitized_candidate_evidence_snapshot`.
- `live_risk_detected=false`.
- `live_submit_recommended_now=0`.
- `db_write_made=false`.
- `queue_mutation_made=false`.
- `bolt_call_made=false`.
- `edxeix_call_made=false`.
- `aade_call_made=false`.
- Candidate evidence hides raw payload and unmasked customer phone.

## Next safest direction

After v3.2.2 is verified, the next phase is controlled closed-gate live-submit preflight design only. Do not enable live EDXEIX submission until Andreas explicitly requests that separate live-submit update.
