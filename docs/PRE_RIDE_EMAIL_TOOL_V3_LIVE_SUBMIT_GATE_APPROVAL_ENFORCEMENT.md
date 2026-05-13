# V3 Live Submit Scaffold — Gate + Approval Enforcement

This patch updates the disabled V3 live-submit scaffold so it now enforces both:

1. `LiveSubmitGateV3` master gate status.
2. Per-row approval in `pre_ride_email_v3_live_submit_approvals`.

The worker still does not submit to EDXEIX. `PRV3_LIVE_SUBMIT_HARD_ENABLED` remains `false`.

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- No queue status changes.
- Optional audit event mode writes only throttled rows to `pre_ride_email_v3_queue_events`.

## Expected default behavior

With no server live-submit config, the master gate remains closed and the worker reports rows as blocked. This is correct.

A future real submit adapter must pass:

- master gate open,
- row status `live_submit_ready`,
- verified starting point,
- valid per-row approval,
- future-time checks,
- final required payload checks.
