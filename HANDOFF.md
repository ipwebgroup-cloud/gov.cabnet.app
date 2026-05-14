# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Current focus

Continue V3 development only. Do not touch V0 production/manual helper files or dependencies.

## V0 / V3 boundary

- V0 is installed on the laptop and is the current manual/production helper.
- V3 is installed on the PC/server side and is the development/test automation path.
- Andreas will use operational judgment during live rides; V3 does not need to make fallback decisions.
- Do not modify V0 as part of V3 patches.

## V3 state

- V3 queue tables installed.
- V3 intake cron has worked previously.
- V3 submit dry-run worker fixed after starting-point option alias bug.
- V3 starting-point guard working.
- V3 live-readiness worker/page working.
- V3 live-submit scaffold installed but hard-disabled.
- V3 live-submit master gate installed and closed.
- V3 operator approval gate installed.
- V3 adapter contract probe installed with disabled/dry-run adapters only.
- V3 final rehearsal installed.
- V3 automation readiness dashboard installed.
- V3 expiry guard installed and working.
- V3 fast pipeline and pulse runner installed.
- Live submit remains disabled.

## Important 2026-05-14 event

During a real ride, Andreas used V0/manual because V3 needed too much time. The V3 pulse cron log showed:

```text
ERROR: Could not open lock file: /home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
```

The lock/log storage directories were repaired manually:

```bash
install -d -o cabnet -g cabnet -m 750 /home/cabnet/gov.cabnet.app_app/storage/locks
install -d -o cabnet -g cabnet -m 750 /home/cabnet/gov.cabnet.app_app/logs
```

After repair, the pulse cron worker started and showed OK pulse cycles.

## New V3-only patch direction

Patch v3.0.39 adds a V3 storage checker:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
gov.cabnet.app_app/storage/locks/.gitkeep
docs/V0_V3_OPERATIONS_BOUNDARY.md
docs/V3_STORAGE_AND_PULSE_CHECK.md
```

Purpose:

- Keep V3 infrastructure prerequisites visible.
- Prevent lock directory problems from being missed.
- Preserve V0/V3 separation.
- Avoid adding fallback decision software.

## Critical safety rule

Do not enable live EDXEIX submission. Live submit must remain disabled unless Andreas explicitly asks for a live-submit gate change.
