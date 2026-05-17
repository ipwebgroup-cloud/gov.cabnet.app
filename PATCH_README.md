# gov.cabnet.app Patch — v3.2.29 Pre-Ride Transport Rehearsal

## What changed

Adds a read-only pre-ride transport rehearsal packet for captured ready pre-ride EDXEIX candidates.

This is a safety preparation step only. It does not perform EDXEIX HTTP transport.

## Files included

```text
CONTINUE_PROMPT.md
HANDOFF.md
PATCH_README.md
PROJECT_FILE_MANIFEST.md
README.md
SCOPE.md
docs/EDXEIX_PRE_RIDE_TRANSPORT_REHEARSAL_v3.2.29.md
gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php
gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php
public_html/gov.cabnet.app/ops/pre-ride-transport-rehearsal.php
```

## Upload paths

```text
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/HANDOFF.md
/home/cabnet/PATCH_README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/docs/EDXEIX_PRE_RIDE_TRANSPORT_REHEARSAL_v3.2.29.md
/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-transport-rehearsal.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-transport-rehearsal.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php --latest-ready=1 --json
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-transport-rehearsal.php
```

## Expected result

The rehearsal reports either:

```text
PRE_RIDE_TRANSPORT_REHEARSAL_READY
```

or a blocker such as:

```text
one_shot_readiness_packet_not_ready
candidate_pickup_not_30_min_future
edxeix_session_not_ready
payload_missing_started_at
```

No EDXEIX submit is performed in either case.
