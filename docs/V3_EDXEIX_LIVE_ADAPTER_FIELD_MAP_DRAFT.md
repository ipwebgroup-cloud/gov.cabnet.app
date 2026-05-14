# V3 EDXEIX Live Adapter Field Map Draft

Version: `v3.0.50-v3-proof-dashboard`

This is a draft for the next phase. It is not a live-submit implementation.

## V3 payload to EDXEIX form fields

| V3 field | EDXEIX form field | Notes |
|---|---|---|
| `lessorId` / queue `lessor_id` | `lessor` | Must be verified for the row. |
| `driverId` / queue `driver_id` | `driver` | Must be mapped from Bolt driver. |
| `vehicleId` / queue `vehicle_id` | `vehicle` | Must be mapped from Bolt vehicle. |
| `startingPointId` / queue `starting_point_id` | `starting_point` | Must exist in `pre_ride_email_v3_starting_point_options`. |
| `passengerName` / queue `customer_name` | `lessee_name` | Customer name from pre-ride email. |
| `customer_phone` | optional passenger/contact field | Depends on EDXEIX form behavior. |
| `pickupAddress` / queue `pickup_address` | `boarding_point` | Boarding address. |
| `dropoffAddress` / queue `dropoff_address` | `disembark_point` | Drop-off address. |
| `pickupDateTime` / queue `pickup_datetime` | `started_at` | Local EEST datetime. |
| `endDateTime` / queue `estimated_end_datetime` | `ended_at` | Estimated end from pre-ride email. |
| `priceAmount` / queue `price_amount` | `price` | Use normalized amount from estimated price range. |
| `orderReference` | optional reference/notes field | May be empty for forwarded test emails. |

## Required safety conditions before any future live adapter can run

- Real eligible future Bolt trip.
- Not synthetic/forwarded test.
- Not past or expired.
- Not cancelled or terminal.
- Not EMT8640.
- Driver mapped.
- Vehicle mapped.
- Lessor mapped.
- Starting point operator-verified.
- Payload audit passes.
- Final rehearsal passes except for intentional closed gate blocks.
- Operator approval valid.
- Master config enabled.
- Mode is `live`.
- Adapter is real and not disabled.
- Hard enable flag is true.
- Required acknowledgement phrase is present.

Until all of the above are true and Andreas explicitly approves, live submit remains disabled.
