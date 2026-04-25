# Safe Operations Console Landing Page

This patch replaces the legacy all-in-one `/ops/index.php` route with a safe, read-only landing page.

## Purpose

The old index route included broad POST actions such as saving EDXEIX session data, creating manual bookings, queueing jobs, processing jobs, and running sync actions. Those actions are no longer appropriate for the current project posture.

The new page is only a navigation hub for current guarded workflow tools:

- `/ops/readiness.php`
- `/ops/future-test.php`
- `/ops/mappings.php`
- `/ops/jobs.php`
- `/ops/bolt-live.php`
- `/ops/test-booking.php`
- `/ops/cleanup-lab.php`
- diagnostic JSON endpoints

## Safety

The new `/ops/index.php`:

- does not call Bolt
- does not call EDXEIX
- does not write to the database
- does not create bookings
- does not create queue jobs
- does not process jobs
- does not save EDXEIX sessions
- does not enable live submission

Live EDXEIX submission remains disabled.
