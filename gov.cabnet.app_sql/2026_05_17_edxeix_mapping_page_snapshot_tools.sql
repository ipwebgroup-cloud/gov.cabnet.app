-- gov.cabnet.app
-- EDXEIX mapping page snapshot tools
-- File: gov.cabnet.app_sql/2026_05_17_edxeix_mapping_page_snapshot_tools.sql
--
-- Purpose:
-- - Support /ops/mappings.php EDXEIX dropdown snapshot import.
-- - Allow mapping rows to store the EDXEIX lessor/company ID beside driver/vehicle IDs.
--
-- Safety:
-- - Additive only.
-- - No deletes.
-- - No live-submit gate changes.
-- - Does not call Bolt, EDXEIX, AADE, or create submission jobs.
-- - Snapshot tables store dropdown IDs/labels only; no cookies, tokens, credentials, sessions, or raw payloads.

ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_driver_id;

ALTER TABLE mapping_vehicles
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_vehicle_id;

CREATE INDEX IF NOT EXISTS idx_mapping_drivers_edxeix_lessor_id ON mapping_drivers (edxeix_lessor_id);
CREATE INDEX IF NOT EXISTS idx_mapping_vehicles_edxeix_lessor_id ON mapping_vehicles (edxeix_lessor_id);

CREATE TABLE IF NOT EXISTS edxeix_export_lessors (
  lessor_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  lessor_label VARCHAR(255) NOT NULL,
  driver_count INT UNSIGNED NOT NULL DEFAULT 0,
  vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
  starting_point_count INT UNSIGNED NOT NULL DEFAULT 0,
  export_generated_at DATETIME NULL,
  source_url VARCHAR(500) NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edxeix_export_drivers (
  lessor_id BIGINT UNSIGNED NOT NULL,
  edxeix_driver_id BIGINT UNSIGNED NOT NULL,
  driver_label VARCHAR(255) NOT NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (lessor_id, edxeix_driver_id),
  KEY idx_edxeix_driver_id (edxeix_driver_id),
  KEY idx_driver_label (driver_label),
  KEY idx_lessor_driver_label (lessor_id, driver_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edxeix_export_vehicles (
  lessor_id BIGINT UNSIGNED NOT NULL,
  edxeix_vehicle_id BIGINT UNSIGNED NOT NULL,
  vehicle_label VARCHAR(255) NOT NULL,
  plate_norm VARCHAR(50) NOT NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (lessor_id, edxeix_vehicle_id),
  KEY idx_edxeix_vehicle_id (edxeix_vehicle_id),
  KEY idx_plate_norm (plate_norm),
  KEY idx_lessor_plate_norm (lessor_id, plate_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edxeix_export_starting_points (
  lessor_id BIGINT UNSIGNED NOT NULL,
  edxeix_starting_point_id BIGINT UNSIGNED NOT NULL,
  starting_point_label TEXT NOT NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (lessor_id, edxeix_starting_point_id),
  KEY idx_edxeix_starting_point_id (edxeix_starting_point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification: local schema/snapshot status only.
SELECT 'mapping_drivers.edxeix_lessor_id' AS check_name,
       COUNT(*) AS column_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'mapping_drivers'
  AND COLUMN_NAME = 'edxeix_lessor_id';

SELECT 'mapping_vehicles.edxeix_lessor_id' AS check_name,
       COUNT(*) AS column_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'mapping_vehicles'
  AND COLUMN_NAME = 'edxeix_lessor_id';

SELECT 'edxeix_export_tables' AS check_name,
       COUNT(*) AS tables_present
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'edxeix_export_lessors',
    'edxeix_export_drivers',
    'edxeix_export_vehicles',
    'edxeix_export_starting_points'
  );
