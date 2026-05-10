-- gov.cabnet.app v6.6.8 optional additive mapping columns
-- Purpose: allow driver/vehicle mappings to carry the EDXEIX company/lessor ID.
-- Safe/additive: no data deletion, no data modification except new nullable columns.
-- Run only if you want the DB to resolve the correct lessor automatically from driver/vehicle.

ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id bigint(20) UNSIGNED DEFAULT NULL AFTER edxeix_driver_id;

ALTER TABLE mapping_vehicles
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id bigint(20) UNSIGNED DEFAULT NULL AFTER edxeix_vehicle_id;

CREATE INDEX IF NOT EXISTS idx_mapping_drivers_edxeix_lessor_id ON mapping_drivers (edxeix_lessor_id);
CREATE INDEX IF NOT EXISTS idx_mapping_vehicles_edxeix_lessor_id ON mapping_vehicles (edxeix_lessor_id);
