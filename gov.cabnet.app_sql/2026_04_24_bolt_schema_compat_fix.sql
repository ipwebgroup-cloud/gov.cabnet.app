-- gov.cabnet.app Bolt schema compatibility fix
-- Purpose:
--   Fixes legacy NOT NULL/no-default columns that blocked live inserts:
--   - mapping_drivers.edxeix_driver_id
--   - mapping_vehicles.edxeix_vehicle_id
--   - source_type/source_system defaults on legacy tables
--
-- If a column does not exist on your local schema, skip that statement or use
-- /bolt_schema_fix.php, which checks columns before altering.

SET NAMES utf8mb4;

ALTER TABLE `mapping_drivers`
  MODIFY COLUMN `edxeix_driver_id` BIGINT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE `mapping_vehicles`
  MODIFY COLUMN `edxeix_vehicle_id` BIGINT UNSIGNED NULL DEFAULT NULL;

-- Run these only if source_type exists in the table.
ALTER TABLE `bolt_raw_payloads`
  MODIFY COLUMN `source_type` VARCHAR(32) NOT NULL DEFAULT 'bolt';

ALTER TABLE `normalized_bookings`
  MODIFY COLUMN `source_type` VARCHAR(32) NOT NULL DEFAULT 'bolt';

-- Safe defaults for bridge flags if present.
ALTER TABLE `normalized_bookings`
  MODIFY COLUMN `edxeix_ready` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `normalized_bookings`
  MODIFY COLUMN `is_scheduled` TINYINT(1) NOT NULL DEFAULT 0;

-- Seed verified known EDXEIX mappings where matching Bolt rows already exist.
UPDATE `mapping_drivers`
SET `edxeix_driver_id` = 17585
WHERE `external_driver_id` = '57256761-d21b-4940-a3ca-bdcec5ef6af1'
  AND (`edxeix_driver_id` IS NULL OR `edxeix_driver_id` = 0);

UPDATE `mapping_vehicles`
SET `edxeix_vehicle_id` = 13799
WHERE (`external_vehicle_id` = '3a008a4e-d81e-40ad-9414-8b4ef57d43e3' OR `plate` = 'EMX6874')
  AND (`edxeix_vehicle_id` IS NULL OR `edxeix_vehicle_id` = 0);

UPDATE `mapping_vehicles`
SET `edxeix_vehicle_id` = 5949
WHERE (`external_vehicle_id` = 'bd2c05a4-fab3-4329-865d-1170d9e6c997' OR `plate` = 'EHA2545')
  AND (`edxeix_vehicle_id` IS NULL OR `edxeix_vehicle_id` = 0);
