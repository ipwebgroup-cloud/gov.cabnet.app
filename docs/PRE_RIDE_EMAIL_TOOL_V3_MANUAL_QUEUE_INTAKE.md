# V3 Manual Queue Intake

Adds the first V3-only queue write step to the isolated pre-ride email V3 tool.

## Scope

- Route: `/ops/pre-ride-email-toolv3.php`
- Production route untouched: `/ops/pre-ride-email-tool.php`
- Queue tables used only when the operator explicitly clicks the new button:
  - `pre_ride_email_v3_queue`
  - `pre_ride_email_v3_queue_events`

## Safety

The page does not auto-queue. It inserts only candidates that pass all gates:

- Parser complete
- EDXEIX IDs mapped
- Pickup is at least 20 minutes in the future

It does not write to:

- `submission_jobs`
- `submission_attempts`

It does not call EDXEIX, does not call AADE, and does not mark email as processed.

## New operator action

When the V3 queue schema is installed and at least one candidate is future-ready, the page shows:

`Queue ready candidates to V3 table`

The button requires browser confirmation before inserting rows into the V3-only queue table.

## Idempotency

Rows use the deterministic V3 `dedupe_key` and `INSERT IGNORE`, so repeated clicks do not create duplicate queue rows.

Inserted rows also create a `manual_queue_intake` event in `pre_ride_email_v3_queue_events`.
