# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

Current patch: v3.2.1 — Real Future Candidate Watch Snapshot
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
- No Bolt calls from v3.2.1.
- No EDXEIX calls from v3.2.1.
- No AADE calls from v3.2.1.
- No DB writes from v3.2.1.
- No queue mutations from v3.2.1.
- No SQL changes.
- No route moves/deletes/redirects.

## Latest verified state before v3.2.1

v3.2.0 production verification passed:

- CLI syntax ok.
- Ops page syntax ok.
- `_shell.php` syntax ok.
- `_ops-nav.php` syntax ok.
- CLI returned `ok=true`.
- DB connected to `cabnet_gov`.
- live gate exists and is closed.
- `future_possible_real_rows=0`.
- `operator_alerts_appropriate=0`.
- `live_risk_detected=false`.
- `live_submit_recommended_now=0`.
- web route returned HTTP 302 to login.

## v3.2.1 changes

- Adds compact one-shot watch snapshot output to the existing read-only candidate capture readiness CLI.
- Adds `--watch-json` / `--snapshot-json` output.
- Adds `--status-line` output for manual terminal polling.
- Adds an Operator Watch Snapshot section to the Ops readiness page.
- Fixes Latest rows table column alignment on the Ops page.
- Normalizes the historical shell side-note wording: `in v3.1.6`.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --watch-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.1\|inv3.1.6\|Watch Snapshot\|status-line" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
```

## Expected result

- `ok=true`
- version `v3.2.1-v3-real-future-candidate-watch-snapshot`
- `live_risk_detected=false`
- `live_submit_recommended_now=0`
- `db_write_made=false`
- `queue_mutation_made=false`
- `bolt_call_made=false`
- `edxeix_call_made=false`
- `aade_call_made=false`
- No `inv3.1.6` typo token present.

## Next safest direction

Continue observing. When a real future possible-real candidate appears, use the v3.2.1 snapshot/status-line output and Ops board to inspect completeness and missing fields while the live gate remains closed.
