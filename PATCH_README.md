# gov.cabnet.app patch — v3.2.1 Real Future Candidate Watch Snapshot

## What changed

- Extended the existing v3.2.0 read-only capture readiness CLI to v3.2.1.
- Added compact `--watch-json` / `--snapshot-json` output.
- Added `--status-line` output for manual terminal polling.
- Added an Operator Watch Snapshot section to the Ops readiness page.
- Fixed the Latest rows scanned table column alignment.
- Normalized the historical shared-shell side note wording from the live typo `inv3.1.6` to `in v3.1.6`.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/_ops-nav.php
docs/V3_REAL_FUTURE_CANDIDATE_WATCH_SNAPSHOT_20260515.md
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
/home/cabnet/docs/V3_REAL_FUTURE_CANDIDATE_WATCH_SNAPSHOT_20260515.md
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

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --watch-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.1\|inv3.1.6\|Watch Snapshot\|status-line" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
```

## Expected result

- Syntax passes.
- CLI JSON returns `ok=true` and version `v3.2.1-v3-real-future-candidate-watch-snapshot`.
- Watch JSON returns `snapshot_mode=read_only_one_shot_operator_watch_snapshot`.
- Status line returns compact action/severity/counts.
- If no real future candidate is visible, expected status includes:

```text
action=WAIT_NO_CANDIDATE | severity=clear | future=0 | review=0 | alerts=0 | urgent=0 | live_risk=no
```

- Web route remains protected by login and returns HTTP 302 when unauthenticated.
- `inv3.1.6` should not appear.

## Git commit title

```text
Add V3 real future candidate watch snapshot
```

## Git commit description

```text
- Add compact read-only watch snapshot output to the V3 real future candidate capture readiness CLI.
- Add --watch-json / --snapshot-json and --status-line CLI modes for manual operator polling.
- Add an Operator Watch Snapshot section to the Ops readiness page.
- Fix Latest rows scanned table column alignment.
- Normalize the historical shared-shell v3.1.6 side-note wording.
- Keep production Pre-Ride Tool untouched and live EDXEIX submission disabled.
- No SQL changes, DB writes, queue mutations, Bolt calls, EDXEIX calls, AADE calls, cron jobs, or notifications.
```
