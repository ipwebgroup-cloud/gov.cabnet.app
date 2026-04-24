-- gov.cabnet.app verified Bolt -> EDXEIX mappings TEMPLATE
-- GitHub-safe placeholder copy.
-- Replace placeholders on the server only. Do not commit real driver names, plates, UUIDs, or EDXEIX IDs.
-- Safe to run more than once after replacing placeholders. No EDXEIX submission is performed.

-- Driver mapping example
UPDATE mapping_drivers
SET
  source_system = 'bolt',
  source_type = 'bolt',
  external_driver_name = 'REPLACE_DRIVER_DISPLAY_NAME',
  edxeix_driver_id = REPLACE_EDXEIX_DRIVER_ID,
  updated_at = NOW()
WHERE external_driver_id = 'REPLACE_BOLT_DRIVER_UUID';

-- Vehicle mapping example
UPDATE mapping_vehicles
SET
  source_system = 'bolt',
  source_type = 'bolt',
  plate = 'REPLACE_PLATE',
  external_vehicle_name = 'REPLACE_VEHICLE_DISPLAY_NAME',
  vehicle_model = 'REPLACE_VEHICLE_MODEL',
  edxeix_vehicle_id = REPLACE_EDXEIX_VEHICLE_ID,
  updated_at = NOW()
WHERE external_vehicle_id = 'REPLACE_BOLT_VEHICLE_UUID'
   OR plate = 'REPLACE_PLATE';
