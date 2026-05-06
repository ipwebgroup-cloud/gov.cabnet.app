# Bolt Mail Intake Expiry Maintenance

Patch v3.9 adds automatic cleanup for stale Bolt pre-ride mail intake rows.

## Purpose

A `future_candidate` row is actionable only while its pickup time is still in the future. If the pickup time passes before an operator creates a local preflight booking, the row must no longer remain actionable.

## Behavior

Every cron import run now also expires open rows where:

- `parse_status = 'parsed'`
- `safety_status IN ('future_candidate', 'blocked_too_soon', 'needs_review')`
- `linked_booking_id IS NULL`
- `parsed_pickup_at <= current app time`

Those rows are changed to:

- `safety_status = 'blocked_past'`
- `rejection_reason` includes the expiry note

## Safety contract

This maintenance does not:

- create normalized bookings
- create submission jobs
- call Bolt
- call EDXEIX
- submit anything live

It only closes stale intake rows so old future candidates cannot be approved later.
