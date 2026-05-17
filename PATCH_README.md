# gov.cabnet.app Patch — Pre-Ride One-Shot Readiness Packet v3.2.27

Generated for Andreas on 2026-05-17.

## What changed

This patch adds a read-only readiness packet for the pre-ride EDXEIX automation path after v3.2.26 successfully parsed and captured a future pre-ride candidate as `candidate_id=2`.

The new packet verifies:

- captured pre-ride candidate status;
- future guard still passes;
- required EDXEIX payload fields are present;
- mapping remains trusted/resolved;
- duplicate success is not already recorded for the payload hash;
- live-submit gates are not unexpectedly already enabled;
- EDXEIX session readiness is visible.

No transport is performed.

## Files included

- `CONTINUE_PROMPT.md`
- `HANDOFF.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_PRE_RIDE_ONE_SHOT_READINESS_v3.2.27.md`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php`
- `public_html/gov.cabnet.app/ops/pre-ride-one-shot-readiness.php`

## Exact upload paths

Upload/replace:

```text
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/HANDOFF.md
/home/cabnet/PATCH_README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/docs/EDXEIX_PRE_RIDE_ONE_SHOT_READINESS_v3.2.27.md
/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-readiness.php
```

For local GitHub Desktop repo, extract this ZIP at the repository root. The ZIP root mirrors the repo/live layout directly and has no wrapper folder.

## SQL to run

None.

The additive v3.2.22 table is already installed and candidate metadata was captured as `candidate_id=2`.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-readiness.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php --candidate-id=2 --json
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php --latest-ready=1 --json
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-one-shot-readiness.php?candidate_id=2
```

## Expected result

If candidate 2 is still at least 30 minutes in the future:

```text
classification.code: PRE_RIDE_ONE_SHOT_READY_PACKET
ready_for_supervised_one_shot: true
transport_performed: false
```

If the trip is too close or past, expected safe blocker:

```text
candidate_pickup_not_30_min_future
```

## Git commit title

Add pre-ride one-shot readiness packet

## Git commit description

Adds a read-only readiness packet for captured pre-ride EDXEIX candidates. The packet verifies future guard, payload completeness, trusted mapping, duplicate success state, session readiness, and disabled live gates before allowing the project to proceed to a later supervised one-shot transport patch. No EDXEIX transport, AADE call, queue job, normalized booking write, cron, or live-submit config write is performed.
