# EDXEIX Form Contract Verifier v3.2

Adds `/ops/edxeix-form-contract.php`.

## Purpose

Compare local EDXEIX payload preview field names against the authenticated EDXEIX lease-agreement form fields confirmed by the GET-only target matrix.

## Confirmed observed form fields

The authenticated GET-only target matrix confirmed the lease creation page at:

`/dashboard/lease-agreement/create`

with field names including:

- `_token`
- `broker`
- `lessor`
- `lessee[type]`
- `lessee[name]`
- `lessee[vat_number]`
- `lessee[legal_representative]`
- `coordinates`
- `drafted_at`
- `started_at`
- `ended_at`
- `price`
- `driver`
- `vehicle`
- `starting_point_id`
- `boarding_point`
- `disembark_point`

## Safety

The page:
- does not call Bolt
- does not call EDXEIX
- does not POST
- reads local config/session metadata and recent normalized bookings only
- does not write database rows or files
- does not stage jobs
- does not update mappings
- does not print cookies, token values, raw session JSON, or passenger payload values
- does not enable live submission
