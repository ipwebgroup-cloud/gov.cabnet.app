# Bolt Mail Intake → Preflight Candidate Bridge

Version: v3.7  
Project: gov.cabnet.app Bolt → EDXEIX bridge

## Purpose

This patch connects parsed Bolt pre-ride email intake rows to the existing local normalized booking and EDXEIX preflight workflow.

The bridge only accepts rows where:

- `parse_status = parsed`
- `safety_status = future_candidate`
- pickup time still passes the configured future guard at approval time
- driver mapping exists
- vehicle mapping exists
- starting point mapping or configured default exists

## Safety boundary

This patch does **not** submit to EDXEIX.

It does **not** create rows in `submission_jobs`.

It only allows a guarded operator to create a local `normalized_bookings` row for preflight review.

Blocked rows remain blocked:

- `blocked_past`
- `blocked_too_soon`
- `needs_review`
- `rejected`

## New Ops URL

```text
https://gov.cabnet.app/ops/mail-preflight.php?key=YOUR_INTERNAL_API_KEY
```

## Workflow

1. Gmail forwards Bolt Ride details emails to `bolt-bridge@gov.cabnet.app`.
2. Cron imports messages into `bolt_mail_intake`.
3. Operator opens `/ops/mail-preflight.php`.
4. Operator previews a `future_candidate` row.
5. If mappings and time guard pass, operator clicks **Create local preflight booking**.
6. The system creates one local `normalized_bookings` row and links it from `bolt_mail_intake.linked_booking_id`.
7. Operator reviews `/bolt_edxeix_preflight.php?limit=30`.
8. Live EDXEIX submission remains disabled.

## Verification SQL

```sql
SELECT id, customer_name, driver_name, vehicle_plate, parsed_pickup_at, parse_status, safety_status, linked_booking_id
FROM bolt_mail_intake
ORDER BY id DESC
LIMIT 20;
```

```sql
SELECT id, source, source_system, source_trip_id, customer_name, driver_name, vehicle_plate, started_at, dedupe_hash
FROM normalized_bookings
WHERE source = 'bolt_mail'
ORDER BY id DESC
LIMIT 20;
```

## Rollback

Remove the new files if needed. The SQL migration only adds an index and is not required to roll back for normal operation.

Do not delete `normalized_bookings` rows without a backup and explicit approval.
