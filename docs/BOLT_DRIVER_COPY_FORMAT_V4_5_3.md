# v4.5.3 — Driver Copy Format Tweaks

## Purpose

Adjust only the driver-facing Bolt pre-ride email copy format.

## Changes

- The driver copy now displays `Estimated end time` as exactly 30 minutes after `Estimated pick-up time`.
- The driver copy now displays only the first value when Bolt provides an estimated price range.
  - Example: `40.00 - 44.00 eur` becomes `40.00 eur`.

## Safety boundary

This change affects only the plain-text email sent to the driver by `BoltMailDriverNotificationService`.

It does not change:

- `bolt_mail_intake` stored parsed values.
- `normalized_bookings`.
- `bolt_mail_dry_run_evidence`.
- EDXEIX payload generation.
- `submission_jobs`.
- `submission_attempts`.
- Live EDXEIX submission state.

Live EDXEIX submission remains disabled unless explicitly enabled by Andreas in a future live-submit patch.
