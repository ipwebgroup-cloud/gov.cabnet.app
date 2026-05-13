# V3 Pre-Ride Email Tool — Submit Control Panel

Adds operator-controlled V3 queue actions to the isolated queue dashboard.

## Route

`/ops/pre-ride-email-v3-queue.php`

## What changed

Selected V3 queue rows now show a submit control panel with explicit operator actions:

- Mark reviewed
- Mark submit dry-run ready
- Reset to queued
- Block row

## Safety

These actions write only to the isolated V3 tables:

- `pre_ride_email_v3_queue`
- `pre_ride_email_v3_queue_events`

They do not submit to EDXEIX, do not issue AADE, do not write to production `submission_jobs`, do not write to production `submission_attempts`, and do not modify `/ops/pre-ride-email-tool.php`.

`Mark submit dry-run ready` is blocked unless parser, mapping, future, and required-ID gates pass.
