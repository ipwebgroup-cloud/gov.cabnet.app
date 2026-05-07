# gov.cabnet.app — v4.5.2 Driver Identity Email Resolution

## Purpose

Driver email copies must be associated with the driver, not with the vehicle plate. Drivers may use different cars at any time, so plate-based recipient resolution is unsafe for production.

## Behavior

- Resolves recipients from `mapping_drivers.driver_email`.
- Prefers immutable driver identifiers when available.
- Otherwise resolves by the driver's own name parsed from the Bolt pre-ride email.
- Improves Bolt driver-directory sync name extraction from nested Bolt API payloads.
- Does not use `active_vehicle_plate` as an email-recipient resolver.
- Plate remains visible only as ride context/audit information.

## Safety

This patch does not create EDXEIX jobs, does not create submission attempts, and does not POST to EDXEIX.
