# gov.cabnet.app Patch v3.2.32 — Fix manual V0 closure submitted_at default

## What changed

Fixes the v3.2.31 manual candidate closure CLI/web library so an empty `submitted_at` defaults to current server time instead of sending an empty string to MariaDB.

## Files included

- gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php
- gov.cabnet.app_app/cli/pre_ride_candidate_mark_manual.php
- public_html/gov.cabnet.app/ops/pre-ride-candidate-closure.php
- gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php
- gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php
- public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php
- docs/EDXEIX_PRE_RIDE_CANDIDATE_CLOSURE_v3.2.32.md
- HANDOFF.md
- CONTINUE_PROMPT.md
- README.md
- SCOPE.md
- PROJECT_FILE_MANIFEST.md

## SQL

None. The v3.2.31 closure table is already installed.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_mark_manual.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-candidate-closure.php
```

Then rerun the candidate 4 manual V0 mark command.

## Safety

No EDXEIX transport, AADE call, queue job, normalized booking write, live config write, cron, or V0 production change.
