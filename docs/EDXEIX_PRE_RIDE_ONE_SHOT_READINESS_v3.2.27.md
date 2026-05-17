# EDXEIX Pre-Ride One-Shot Readiness Packet v3.2.27

Generated for Andreas on 2026-05-17.

## Purpose

v3.2.27 adds a locked readiness packet for the pre-ride EDXEIX automation path. It is the bridge between a parsed/captured future pre-ride candidate and a later supervised one-shot transport trace.

## Safety posture

This patch is read-only/readiness-only.

It does not:

- submit to EDXEIX;
- call AADE/myDATA;
- create queue jobs;
- create or modify `normalized_bookings`;
- write `live_submit.php`;
- enable cron or unattended workers.

## New tools

CLI:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php --candidate-id=2 --json
```

Alternative source modes:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php --latest-ready=1 --json
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php --latest-mail=1 --json
```

Ops page:

```text
https://gov.cabnet.app/ops/pre-ride-one-shot-readiness.php?candidate_id=2
```

## Expected successful classification

```text
PRE_RIDE_ONE_SHOT_READY_PACKET
```

This means the candidate is ready for the next supervised one-shot transport patch. It still does not submit.

## Expected blockers

Possible blockers include:

- candidate is no longer at least 30 minutes in the future;
- candidate status is not ready;
- payload fields are missing;
- mapping is incomplete or not trusted;
- a duplicate successful payload hash is already known;
- live submit gates are unexpectedly already enabled.

## Current validated state

The previous v3.2.26 validation produced a ready pre-ride candidate and captured it as `candidate_id=2`. This patch should be verified against that candidate first.
