# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

Current patch: v3.2.3 — EDXEIX Payload Preview / Dry-Run Preflight
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
- No Bolt calls from v3.2.3.
- No EDXEIX calls from v3.2.3.
- No AADE calls from v3.2.3.
- No DB writes from v3.2.3.
- No queue mutations from v3.2.3.
- No SQL changes.
- No route moves/deletes/redirects.
- No cron jobs or notifications.

## Verified milestone before v3.2.3

A real-format pre-ride email was manually placed into the Bolt bridge mailbox path and ingested by the V3 queue.

v3.2.1/v3.2.2 confirmed:

- queue id `1457`
- `queue_status=live_submit_ready`
- future possible-real row: 1
- complete future row: 1
- missing required fields: none
- parser_ok: true
- mapping_ok: true
- future_ok_flag: true
- closed-gate operator review candidate: 1
- operator alert appropriate: 1
- live gate expected closed: true
- live risk detected: false
- live submit recommended now: 0
- DB write made by observation/evidence tools: false
- queue mutation made by observation/evidence tools: false
- Bolt/EDXEIX/AADE calls made by observation/evidence tools: false

Interpretation: the real-format pre-ride email path is proven through parsing, mapping, queue insertion, closed-gate readiness detection, and sanitized evidence snapshot.

## v3.2.3 changes

- Adds read-only EDXEIX payload preview / dry-run preflight to the existing V3 capture readiness CLI.
- Adds `--edxeix-preview-json`, `--payload-preview-json`, and `--dry-run-preflight-json` CLI modes.
- Adds EDXEIX Payload Preview / Dry-Run Preflight section to the Ops readiness page.
- Shows normalized EDXEIX candidate fields in sanitized form.
- Keeps passenger phone masked in preview.
- Keeps live submit disabled and blocked by design.
- Updates shared shell/nav text to v3.2.3.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --watch-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --evidence-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --edxeix-preview-json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.3\|edxeix-preview-json\|EDXEIX Payload Preview\|dry_run_preview\|live_submit_allowed_now" \
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Expected result

- Syntax passes.
- Watch JSON still works.
- Status line still works.
- Evidence JSON still works.
- EDXEIX preview JSON returns `snapshot_mode=read_only_edxeix_payload_preview_dry_run_preflight`.
- If a complete future candidate exists, expected `preflight_outcome=dry_run_preview_passed_live_submit_still_blocked`.
- `dry_run_only=true`.
- `live_submit_allowed_now=false`.
- `edxeix_call_made=false`.
- `db_write_made=false`.
- `queue_mutation_made=false`.

## Next safest direction

After v3.2.3 is verified and committed, the next phase can design a controlled, explicit, single-candidate live-submit gate. Do not enable live EDXEIX submission until Andreas explicitly requests that separate live-submit update and all preflight gates pass.

## v3.2.4 — Expired Candidate Safety Regression Audit
- Adds read-only expired-candidate safety regression audit to the V3 real future candidate capture readiness CLI/Ops page.
- New CLI modes: `--expired-safety-json`, `--stale-ready-audit-json`, `--regression-audit-json`.
- Detects stale `live_submit_ready` rows whose pickup time is no longer future-safe.
- Proves stale ready rows are not eligible for closed-gate review, operator alert, or live submission.
- Keeps live EDXEIX submission disabled.
- No SQL changes, DB writes, queue mutations, Bolt calls, EDXEIX calls, AADE calls, cron jobs, notifications, or Pre-Ride Tool changes.


## v3.2.5 — Controlled Live-Submit Readiness Checklist
- Adds read-only go/no-go snapshot via `--live-readiness-json`, `--controlled-live-readiness-json`, and `--go-no-go-json`.
- Aggregates watch snapshot, evidence snapshot, EDXEIX dry-run preview, expired candidate safety audit, live gate posture, DB/queue availability, and manual gates.
- Does not enable live submit and performs no DB writes, queue mutations, Bolt calls, EDXEIX calls, AADE calls, cron jobs, or notifications.
- Live EDXEIX submission remains blocked by design and requires an explicit future Andreas request before any live-submit patch is created.


## v3.2.6 — Single-Row Controlled Live-Submit Design Draft

- Added read-only single-row live-submit design draft output.
- New CLI modes: `--single-row-live-design-json`, `--first-live-test-design-json`, `--controlled-live-submit-design-json`.
- No live submitter was added; live EDXEIX submission remains blocked.
- No SQL changes, DB writes, queue mutations, Bolt calls, EDXEIX calls, AADE calls, cron jobs, notifications, or live-submit enablement.
