# gov.cabnet.app patch — v3.2.3 EDXEIX Payload Preview / Dry-Run Preflight

## What changed

- Extended the existing v3.2.2 read-only capture readiness CLI to v3.2.3.
- Added read-only `--edxeix-preview-json` output.
- Added aliases: `--payload-preview-json` and `--dry-run-preflight-json`.
- Added EDXEIX Payload Preview / Dry-Run Preflight section to the Ops readiness page.
- Preserved v3.2.2 `--watch-json`, `--snapshot-json`, `--status-line`, and `--evidence-json` behavior.
- Updated shared shell/nav comments to v3.2.3.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/_ops-nav.php
docs/V3_EDXEIX_PAYLOAD_PREVIEW_DRY_RUN_PREFLIGHT_20260515.md
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
/home/cabnet/docs/V3_EDXEIX_PAYLOAD_PREVIEW_DRY_RUN_PREFLIGHT_20260515.md
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
- EDXEIX preview JSON returns:

```text
snapshot_mode=read_only_edxeix_payload_preview_dry_run_preflight
```

- If a complete future candidate exists, preview may return:

```text
preflight_outcome=dry_run_preview_passed_live_submit_still_blocked
```

- Safety confirmation remains:

```text
dry_run_only=true
live_submit_allowed_now=false
edxeix_call_made=false
db_write_made=false
queue_mutation_made=false
bolt_call_made=false
aade_call_made=false
```

## Git commit title

```text
Add V3 EDXEIX payload dry-run preview
```

## Git commit description

```text
- Add read-only EDXEIX payload preview / dry-run preflight output to the V3 real future candidate capture readiness CLI.
- Add --edxeix-preview-json, --payload-preview-json, and --dry-run-preflight-json CLI modes.
- Add EDXEIX Payload Preview / Dry-Run Preflight section to the Ops readiness page.
- Show normalized EDXEIX candidate fields in sanitized form while masking passenger phone and hiding raw payloads.
- Preserve watch-json/status-line/evidence-json behavior and keep live EDXEIX submission disabled.
- No SQL changes, DB writes, queue mutations, Bolt calls, EDXEIX calls, AADE calls, cron jobs, notifications, or live-submit enablement.
```
