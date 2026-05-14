# V3 → EDXEIX Live Adapter Field Map Draft

Status: draft for closed-gate preparation only.

Live submit remains disabled.

| V3 payload / queue field | EDXEIX form intent |
|---|---|
| `lessor_id` / `lessorId` | Lessor / company selector |
| `driver_id` / `driverId` | Driver selector |
| `vehicle_id` / `vehicleId` | Vehicle selector |
| `starting_point_id` / `startingPointId` | Starting point selector |
| `customer_name` / `passengerName` | Passenger / lessee name |
| `customer_phone` / `passengerPhone` | Passenger phone, if accepted by target form |
| `pickup_address` / `pickupAddress` | Boarding point |
| `dropoff_address` / `dropoffAddress` | Disembark point |
| `pickup_datetime` / `pickupDateTime` | Transfer start datetime |
| `estimated_end_datetime` / `endDateTime` | Transfer end datetime |
| `price_amount` / `priceAmount` | Price |
| `price_text` | Operator-readable price evidence |

## Safety checklist before any real adapter

The adapter must remain blocked unless all are true:

- real eligible future Bolt trip
- not synthetic/forwarded proof email
- not past, expired, cancelled, terminal, or invalid
- not EMT8640 exempt vehicle
- driver/vehicle/lessor mapped
- starting point operator-verified
- payload audit OK
- final rehearsal OK except intentional gate blocks
- valid operator approval
- master gate enabled
- mode live
- adapter real
- hard enable true
- required acknowledgement phrase present

Until explicitly approved, the adapter must not submit to EDXEIX.
