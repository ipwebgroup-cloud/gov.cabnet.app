-- gov.cabnet.app
-- Mapping Workbench V3 support migration
-- File: gov.cabnet.app_sql/2026_05_17_mapping_workbench_v3.sql
--
-- Purpose:
-- - Support /ops/mapping-workbench-v3.php.
-- - Store EDXEIX lessor/company ownership on driver/vehicle mapping rows.
-- - Store EDXEIX dropdown snapshot exports for local validation and suggestions.
-- - Ensure local mapping update audit table exists.
--
-- Safety:
-- - Additive only.
-- - No deletes.
-- - No live-submit gate changes.
-- - Does not call Bolt, EDXEIX, AADE, or create submission jobs.
-- - Snapshot tables store dropdown IDs/labels only; no cookies, tokens, credentials, sessions, or raw payloads.
-- - phpMyAdmin/cPanel-safe: no information_schema verification queries.

SET NAMES utf8mb4;

ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_driver_id;

ALTER TABLE mapping_vehicles
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_vehicle_id;

CREATE INDEX IF NOT EXISTS idx_mapping_drivers_edxeix_lessor_id ON mapping_drivers (edxeix_lessor_id);
CREATE INDEX IF NOT EXISTS idx_mapping_vehicles_edxeix_lessor_id ON mapping_vehicles (edxeix_lessor_id);

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

-- Local verification queries. These read only the current database and do not use information_schema.
SHOW COLUMNS FROM mapping_drivers LIKE 'edxeix_lessor_id';
SHOW COLUMNS FROM mapping_vehicles LIKE 'edxeix_lessor_id';
SHOW TABLES LIKE 'edxeix_export_lessors';
SHOW TABLES LIKE 'edxeix_export_drivers';
SHOW TABLES LIKE 'edxeix_export_vehicles';
SHOW TABLES LIKE 'edxeix_export_starting_points';
