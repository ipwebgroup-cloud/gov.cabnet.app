# EDXEIX Pre-Ride Transport Rehearsal — v3.2.29

## Purpose

v3.2.29 adds a read-only rehearsal packet for the pre-ride automation path.

It is the final non-live preparation step before any future supervised one-shot EDXEIX transport trace.

## Safety contract

This patch does **not**:

- submit to EDXEIX;
- call AADE/myDATA;
- create queue jobs;
- write to `normalized_bookings`;
- write or modify `/home/cabnet/gov.cabnet.app_config/live_submit.php`;
- enable cron or unattended automation.

## New files

```text
gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php
gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php
public_html/gov.cabnet.app/ops/pre-ride-transport-rehearsal.php
```

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-transport-rehearsal.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php --latest-ready=1 --json
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php --candidate-id=2 --json
```

## Web URL

```text
https://gov.cabnet.app/ops/pre-ride-transport-rehearsal.php
https://gov.cabnet.app/ops/pre-ride-transport-rehearsal.php?candidate_id=2
```

## Expected classifications

```text
PRE_RIDE_TRANSPORT_REHEARSAL_READY
PRE_RIDE_TRANSPORT_REHEARSAL_BLOCKED
PRE_RIDE_TRANSPORT_REHEARSAL_ERROR
```

`PRE_RIDE_TRANSPORT_REHEARSAL_READY` means only that all read-only safety checks pass for a later supervised transport patch. It does not mean anything was submitted.

## Next step after this patch

The next patch is sensitive and must not be built or enabled unless Andreas explicitly approves a real one-candidate live test.

Required approval phrase:

```text
Sophion, prepare the supervised pre-ride one-shot EDXEIX transport trace patch. I understand this is for one real eligible future ride only.
```
