# V3 EDXEIX Live Adapter Field Map

Status: draft, closed-gate preparation only.

## Field map

| V3 source | Exported EDXEIX field | Notes |
|---|---|---|
| `lessor_id` / `payload.lessorId` | `lessor` | EDXEIX lessor/company ID |
| `driver_id` / `payload.driverId` | `driver` | EDXEIX driver ID |
| `vehicle_id` / `payload.vehicleId` | `vehicle` | EDXEIX vehicle ID |
| `starting_point_id` / `payload.startingPointId` | `starting_point_id` | Verified against `pre_ride_email_v3_starting_point_options` |
| `customer_name` / `payload.passengerName` | `lessee_name` | Passenger/customer name |
| `customer_phone` / `payload.passengerPhone` | `lessee_phone` | Useful context; form support may vary |
| `pickup_address` / `payload.pickupAddress` | `boarding_point` | Pickup address |
| `dropoff_address` / `payload.dropoffAddress` | `disembark_point` | Drop-off address |
| `pickup_datetime` / `payload.pickupDateTime` | `started_at` | Pickup/start time |
| `estimated_end_datetime` / `payload.endDateTime` | `ended_at` | Estimated end time |
| `price_amount` / `payload.priceAmount` | `price` | Numeric price amount when available |
| `price_text` / `payload.priceText` | `price_text` | Original Bolt price text |

## Safety

This map is for local package export and future adapter design only. It does not open or enable live submit.
