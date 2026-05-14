# HANDOFF — gov.cabnet.app V3 automation

## Current status

V3 closed-gate automation path has been proven through:

- email intake
- V3 queue creation
- starting-point guard
- submit dry-run readiness
- live-submit readiness
- payload audit
- local package export
- operator approval workflow
- final rehearsal
- kill-switch checker
- pre-live switchboard
- future adapter skeleton
- adapter contract probe

Live submit remains disabled.

V0 remains untouched.

## Latest patch

`v3.0.67-v3-adapter-row-simulation`

Adds a read-only simulation layer that builds an EDXEIX field package from a real V3 queue row and calls the local `EdxeixLiveSubmitAdapterV3` skeleton.

Expected safe result:

```text
submitted=false
isLiveCapable=false
simulation_safe=yes
```

## New URLs

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php
```

## New CLI

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php
```

## Safety boundary

No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.
