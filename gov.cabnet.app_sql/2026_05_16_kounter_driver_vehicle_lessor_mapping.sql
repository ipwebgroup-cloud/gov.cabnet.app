-- gov.cabnet.app — Ioannis Kounter EDXEIX mapping patch
-- Date: 2026-05-16
-- Purpose:
--   Map the verified Bolt driver/vehicles for Ioannis Kounter to the correct
--   EDXEIX lessor/company, driver, and vehicle IDs.
--
-- Verified EDXEIX values from browser screenshots:
--   Lessor/company: 2183  ΚΟΥΝΤΕΡ ΙΩΑΝΝΗΣ
--   Driver:         7329  ΚΟΥΝΤΕΡ ΙΩΑΝΝΗΣ
--   Vehicle:        3160  ΧΖΑ3232 / XZA3232
--   Alt vehicle:    13191 ΧΡΜ5435 / XRM5435
--
-- Safety:
--   - Additive/update-only.
--   - No deletes.
--   - No Bolt API calls.
--   - No EDXEIX calls.
--   - Does not create or submit EDXEIX queue jobs.
--   - Safe to run more than once.

START TRANSACTION;

-- Ensure the lessor ownership columns exist. These are nullable and additive.
ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_driver_id;

ALTER TABLE mapping_vehicles
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_vehicle_id;

CREATE INDEX IF NOT EXISTS idx_mapping_drivers_edxeix_lessor_id ON mapping_drivers (edxeix_lessor_id);
CREATE INDEX IF NOT EXISTS idx_mapping_vehicles_edxeix_lessor_id ON mapping_vehicles (edxeix_lessor_id);

-- Ensure audit table exists for manual/SQL mapping changes.
CREATE TABLE IF NOT EXISTS mapping_update_audit (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  table_name VARCHAR(64) NOT NULL,
  row_id BIGINT(20) UNSIGNED NOT NULL,
  field_name VARCHAR(64) NOT NULL,
  old_value VARCHAR(191) DEFAULT NULL,
  new_value VARCHAR(191) NOT NULL,
  changed_by VARCHAR(191) DEFAULT NULL,
  remote_ip VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mapping_update_audit_table_row (table_name, row_id),
  KEY idx_mapping_update_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @patch_name = '2026_05_16_kounter_driver_vehicle_lessor_mapping.sql';
SET @reason = 'Verified Ioannis Kounter EDXEIX driver/vehicle/lessor mapping from 2026-05-16 screenshots.';
SET @lessor_id = 2183;
SET @driver_id = 7329;
SET @driver_uuid = '5365edef-d657-4515-866f-7ef06700092f';
SET @driver_name = 'Ioannis Kounter';
SET @xza_vehicle_id = 3160;
SET @xza_vehicle_uuid = '3748ce59-9ab6-4584-a2aa-2a2ca899ba0f';
SET @xza_plate = 'XZA3232';
SET @xrm_vehicle_id = 13191;
SET @xrm_vehicle_uuid = '879ed1b7-1489-4917-9630-ea6be1b3c72b';
SET @xrm_plate = 'XRM5435';

-- Insert driver if missing; otherwise update existing row(s).
INSERT INTO mapping_drivers
(source_system, external_driver_id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, active_vehicle_uuid, active_vehicle_plate, is_active, created_at, updated_at)
SELECT 'bolt', @driver_uuid, @driver_name, @driver_id, @lessor_id, @xza_vehicle_uuid, @xza_plate, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM mapping_drivers
    WHERE source_system = 'bolt'
      AND (external_driver_id = @driver_uuid OR external_driver_name = @driver_name OR id = 19)
);

-- Audit driver EDXEIX ID changes before update.
INSERT INTO mapping_update_audit
(table_name, row_id, field_name, old_value, new_value, changed_by, remote_ip, user_agent, reason, created_at)
SELECT 'mapping_drivers', id, 'edxeix_driver_id', COALESCE(CAST(edxeix_driver_id AS CHAR), ''), CAST(@driver_id AS CHAR), @patch_name, '', '', @reason, NOW()
FROM mapping_drivers
WHERE source_system = 'bolt'
  AND (id = 19 OR external_driver_id = @driver_uuid OR external_driver_name = @driver_name)
  AND COALESCE(CAST(edxeix_driver_id AS CHAR), '') <> CAST(@driver_id AS CHAR);

INSERT INTO mapping_update_audit
(table_name, row_id, field_name, old_value, new_value, changed_by, remote_ip, user_agent, reason, created_at)
SELECT 'mapping_drivers', id, 'edxeix_lessor_id', COALESCE(CAST(edxeix_lessor_id AS CHAR), ''), CAST(@lessor_id AS CHAR), @patch_name, '', '', @reason, NOW()
FROM mapping_drivers
WHERE source_system = 'bolt'
  AND (id = 19 OR external_driver_id = @driver_uuid OR external_driver_name = @driver_name)
  AND COALESCE(CAST(edxeix_lessor_id AS CHAR), '') <> CAST(@lessor_id AS CHAR);

UPDATE mapping_drivers
SET edxeix_driver_id = @driver_id,
    edxeix_lessor_id = @lessor_id,
    active_vehicle_uuid = @xza_vehicle_uuid,
    active_vehicle_plate = @xza_plate,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND (id = 19 OR external_driver_id = @driver_uuid OR external_driver_name = @driver_name);

-- Insert/update default vehicle XZA3232 / ΧΖΑ3232.
INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, external_vehicle_name, vehicle_model, edxeix_vehicle_id, edxeix_lessor_id, is_active, created_at, updated_at)
SELECT 'bolt', @xza_vehicle_uuid, @xza_plate, 'Mercedes-Benz Vito', 'Mercedes-Benz Vito', @xza_vehicle_id, @lessor_id, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM mapping_vehicles
    WHERE source_system = 'bolt'
      AND (id = 18 OR external_vehicle_id = @xza_vehicle_uuid OR REPLACE(UPPER(plate), ' ', '') IN ('XZA3232', 'ΧΖΑ3232'))
);

INSERT INTO mapping_update_audit
(table_name, row_id, field_name, old_value, new_value, changed_by, remote_ip, user_agent, reason, created_at)
SELECT 'mapping_vehicles', id, 'edxeix_vehicle_id', COALESCE(CAST(edxeix_vehicle_id AS CHAR), ''), CAST(@xza_vehicle_id AS CHAR), @patch_name, '', '', @reason, NOW()
FROM mapping_vehicles
WHERE source_system = 'bolt'
  AND (id = 18 OR external_vehicle_id = @xza_vehicle_uuid OR REPLACE(UPPER(plate), ' ', '') IN ('XZA3232', 'ΧΖΑ3232'))
  AND COALESCE(CAST(edxeix_vehicle_id AS CHAR), '') <> CAST(@xza_vehicle_id AS CHAR);

INSERT INTO mapping_update_audit
(table_name, row_id, field_name, old_value, new_value, changed_by, remote_ip, user_agent, reason, created_at)
SELECT 'mapping_vehicles', id, 'edxeix_lessor_id', COALESCE(CAST(edxeix_lessor_id AS CHAR), ''), CAST(@lessor_id AS CHAR), @patch_name, '', '', @reason, NOW()
FROM mapping_vehicles
WHERE source_system = 'bolt'
  AND (id = 18 OR external_vehicle_id = @xza_vehicle_uuid OR REPLACE(UPPER(plate), ' ', '') IN ('XZA3232', 'ΧΖΑ3232'))
  AND COALESCE(CAST(edxeix_lessor_id AS CHAR), '') <> CAST(@lessor_id AS CHAR);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = @xza_vehicle_id,
    edxeix_lessor_id = @lessor_id,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND (id = 18 OR external_vehicle_id = @xza_vehicle_uuid OR REPLACE(UPPER(plate), ' ', '') IN ('XZA3232', 'ΧΖΑ3232'));

-- Insert/update alternate vehicle XRM5435 / ΧΡΜ5435.
INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, external_vehicle_name, vehicle_model, edxeix_vehicle_id, edxeix_lessor_id, is_active, created_at, updated_at)
SELECT 'bolt', @xrm_vehicle_uuid, @xrm_plate, 'Mercedes-Benz GLB', 'Mercedes-Benz GLB', @xrm_vehicle_id, @lessor_id, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM mapping_vehicles
    WHERE source_system = 'bolt'
      AND (id = 17 OR external_vehicle_id = @xrm_vehicle_uuid OR REPLACE(UPPER(plate), ' ', '') IN ('XRM5435', 'ΧΡΜ5435'))
);

INSERT INTO mapping_update_audit
(table_name, row_id, field_name, old_value, new_value, changed_by, remote_ip, user_agent, reason, created_at)
SELECT 'mapping_vehicles', id, 'edxeix_vehicle_id', COALESCE(CAST(edxeix_vehicle_id AS CHAR), ''), CAST(@xrm_vehicle_id AS CHAR), @patch_name, '', '', @reason, NOW()
FROM mapping_vehicles
WHERE source_system = 'bolt'
  AND (id = 17 OR external_vehicle_id = @xrm_vehicle_uuid OR REPLACE(UPPER(plate), ' ', '') IN ('XRM5435', 'ΧΡΜ5435'))
  AND COALESCE(CAST(edxeix_vehicle_id AS CHAR), '') <> CAST(@xrm_vehicle_id AS CHAR);

INSERT INTO mapping_update_audit
(table_name, row_id, field_name, old_value, new_value, changed_by, remote_ip, user_agent, reason, created_at)
SELECT 'mapping_vehicles', id, 'edxeix_lessor_id', COALESCE(CAST(edxeix_lessor_id AS CHAR), ''), CAST(@lessor_id AS CHAR), @patch_name, '', '', @reason, NOW()
FROM mapping_vehicles
WHERE source_system = 'bolt'
  AND (id = 17 OR external_vehicle_id = @xrm_vehicle_uuid OR REPLACE(UPPER(plate), ' ', '') IN ('XRM5435', 'ΧΡΜ5435'))
  AND COALESCE(CAST(edxeix_lessor_id AS CHAR), '') <> CAST(@lessor_id AS CHAR);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = @xrm_vehicle_id,
    edxeix_lessor_id = @lessor_id,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND (id = 17 OR external_vehicle_id = @xrm_vehicle_uuid OR REPLACE(UPPER(plate), ' ', '') IN ('XRM5435', 'ΧΡΜ5435'));

COMMIT;

-- Verification output: should show driver 7329 / lessor 2183 and vehicles 3160 + 13191 / lessor 2183.
SELECT id, source_system, external_driver_id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, active_vehicle_uuid, active_vehicle_plate, is_active, updated_at
FROM mapping_drivers
WHERE source_system = 'bolt'
  AND (id = 19 OR external_driver_id = @driver_uuid OR external_driver_name = @driver_name)
ORDER BY id;

SELECT id, source_system, external_vehicle_id, plate, external_vehicle_name, vehicle_model, edxeix_vehicle_id, edxeix_lessor_id, is_active, updated_at
FROM mapping_vehicles
WHERE source_system = 'bolt'
  AND (
    id IN (17, 18)
    OR external_vehicle_id IN (@xza_vehicle_uuid, @xrm_vehicle_uuid)
    OR REPLACE(UPPER(plate), ' ', '') IN ('XZA3232', 'ΧΖΑ3232', 'XRM5435', 'ΧΡΜ5435')
  )
ORDER BY plate, id;
