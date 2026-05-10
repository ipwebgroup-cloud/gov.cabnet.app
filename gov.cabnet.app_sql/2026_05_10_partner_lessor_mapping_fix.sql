-- gov.cabnet.app v6.6.10 — partner/executing vehicle lessor mapping fix
-- Purpose: make Efthymios Giakis / ITK7702 resolve to the EDXEIX company where they are registered.
-- Safe/update-only plus insert-if-missing. No destructive changes.

ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_driver_id;

ALTER TABLE mapping_vehicles
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_vehicle_id;

SET @lessor_id = 2307;
SET @driver_name = 'Efthymios Giakis';
SET @driver_id = 17852;
SET @plate = 'ITK7702';
SET @vehicle_id = 11187;

UPDATE mapping_drivers
SET edxeix_driver_id = @driver_id,
    edxeix_lessor_id = @lessor_id,
    active_vehicle_plate = @plate,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND external_driver_name = @driver_name;

INSERT INTO mapping_drivers
(source_system, external_driver_name, edxeix_driver_id, edxeix_lessor_id, active_vehicle_plate, is_active, created_at, updated_at)
SELECT 'bolt', @driver_name, @driver_id, @lessor_id, @plate, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM mapping_drivers
    WHERE source_system = 'bolt'
      AND external_driver_name = @driver_name
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = @vehicle_id,
    edxeix_lessor_id = @lessor_id,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND REPLACE(UPPER(plate),' ','') = REPLACE(UPPER(@plate),' ','');

INSERT INTO mapping_vehicles
(source_system, plate, edxeix_vehicle_id, edxeix_lessor_id, is_active, created_at, updated_at)
SELECT 'bolt', @plate, @vehicle_id, @lessor_id, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM mapping_vehicles
    WHERE source_system = 'bolt'
      AND REPLACE(UPPER(plate),' ','') = REPLACE(UPPER(@plate),' ','')
);

INSERT INTO mapping_starting_points
(internal_key, label, edxeix_starting_point_id, is_active, created_at, updated_at)
VALUES
('default', 'Έδρα μας', '5875309', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
label = VALUES(label),
edxeix_starting_point_id = VALUES(edxeix_starting_point_id),
is_active = 1,
updated_at = NOW();

SELECT id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, active_vehicle_plate, is_active
FROM mapping_drivers
WHERE external_driver_name = @driver_name;

SELECT id, plate, edxeix_vehicle_id, edxeix_lessor_id, is_active
FROM mapping_vehicles
WHERE REPLACE(UPPER(plate),' ','') = REPLACE(UPPER(@plate),' ','');
