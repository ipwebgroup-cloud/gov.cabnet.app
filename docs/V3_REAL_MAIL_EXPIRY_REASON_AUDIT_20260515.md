# V3 Real-Mail Expiry Reason Audit — 2026-05-15

## Purpose

Adds a read-only explanation board for V3 real-mail queue rows that are blocked after pickup time has passed.

The previous real-mail queue health audit showed:

- total rows: 12
- possible real-mail rows: 11
- canary rows: 1
- future active rows: 0
- live-submit-ready rows: 0
- dry-run-ready rows: 0
- rows with last_error: 12
- live risk: false

This audit classifies those rows so the operator can understand that the rows are blocked by the V3 future-safety expiry guard.

## Safety

This package does not:

- call Bolt
- call EDXEIX
- call AADE
- write to the database
- mutate queue rows
- move routes
- delete routes
- add redirects
- touch the production pre-ride tool
- enable live submission

## Expected interpretation

Rows with `v3_queue_row_expired_pickup_not_future_safe` are expected closed-gate behavior once pickup time has passed.

The next operational goal is to observe a real future pre-ride email before the pickup expires, while keeping the live EDXEIX gate closed.
