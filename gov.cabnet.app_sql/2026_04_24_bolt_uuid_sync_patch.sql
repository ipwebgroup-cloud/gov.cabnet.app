-- gov.cabnet.app Bolt UUID sync patch
-- Safe direction: adds columns/tables needed by live Bolt importer.
-- Review on staging/backup first. No secrets here.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bolt_raw_payloads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_system VARCHAR(32) NOT NULL DEFAULT 'bolt',
  source_endpoint VARCHAR(120) NULL,
  external_reference VARCHAR(191) NULL,
  payload_hash CHAR(64) NOT NULL,
  payload_json LONGTEXT NULL,
  raw_json LONGTEXT NULL,
  captured_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_bolt_raw_payload_hash (payload_hash),
  KEY idx_bolt_raw_external_reference (source_system, external_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mapping_drivers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_system VARCHAR(32) NOT NULL DEFAULT 'bolt',
  external_driver_id VARCHAR(191) NOT NULL,
  external_driver_name VARCHAR(255) NULL,
  edxeix_driver_id BIGINT UNSIGNED NULL,
  driver_phone VARCHAR(80) NULL,
  active_vehicle_uuid VARCHAR(191) NULL,
  active_vehicle_plate VARCHAR(32) NULL,
  raw_payload_json LONGTEXT NULL,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mapping_driver_source_external (source_system, external_driver_id),
  KEY idx_mapping_driver_edxeix (edxeix_driver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mapping_vehicles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_system VARCHAR(32) NOT NULL DEFAULT 'bolt',
  external_vehicle_id VARCHAR(191) NULL,
  plate VARCHAR(32) NULL,
  external_vehicle_name VARCHAR(255) NULL,
  vehicle_model VARCHAR(255) NULL,
  edxeix_vehicle_id BIGINT UNSIGNED NULL,
  raw_payload_json LONGTEXT NULL,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mapping_vehicle_source_external (source_system, external_vehicle_id),
  KEY idx_mapping_vehicle_plate (source_system, plate),
  KEY idx_mapping_vehicle_edxeix (edxeix_vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS normalized_bookings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_system VARCHAR(32) NOT NULL DEFAULT 'bolt',
  external_order_id VARCHAR(191) NULL,
  order_reference VARCHAR(191) NULL,
  source_trip_reference VARCHAR(191) NULL,
  driver_external_id VARCHAR(191) NULL,
  driver_name VARCHAR(255) NULL,
  driver_phone VARCHAR(80) NULL,
  vehicle_external_id VARCHAR(191) NULL,
  vehicle_plate VARCHAR(32) NULL,
  vehicle_model VARCHAR(255) NULL,
  passenger_name VARCHAR(255) NULL,
  lessee_name VARCHAR(255) NULL,
  pickup_address TEXT NULL,
  boarding_point TEXT NULL,
  destination_address TEXT NULL,
  disembark_point TEXT NULL,
  price VARCHAR(80) NULL,
  status VARCHAR(80) NULL,
  order_status VARCHAR(80) NULL,
  is_scheduled TINYINT(1) NOT NULL DEFAULT 0,
  started_at DATETIME NULL,
  ended_at DATETIME NULL,
  order_created_at DATETIME NULL,
  raw_payload_id BIGINT UNSIGNED NULL,
  normalized_payload_json LONGTEXT NULL,
  raw_payload_json LONGTEXT NULL,
  edxeix_payload_json LONGTEXT NULL,
  edxeix_ready TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_normalized_source_external (source_system, external_order_id),
  KEY idx_normalized_order_reference (source_system, order_reference),
  KEY idx_normalized_started_at (started_at),
  KEY idx_normalized_status (order_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing columns on existing starter tables. MariaDB supports ADD COLUMN IF NOT EXISTS.
ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS source_system VARCHAR(32) NOT NULL DEFAULT 'bolt',
  ADD COLUMN IF NOT EXISTS external_driver_id VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS external_driver_name VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS edxeix_driver_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS active_vehicle_uuid VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS active_vehicle_plate VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS raw_payload_json LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL;

ALTER TABLE mapping_vehicles
  ADD COLUMN IF NOT EXISTS source_system VARCHAR(32) NOT NULL DEFAULT 'bolt',
  ADD COLUMN IF NOT EXISTS external_vehicle_id VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS plate VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS external_vehicle_name VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS vehicle_model VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS edxeix_vehicle_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS raw_payload_json LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL;

ALTER TABLE bolt_raw_payloads
  ADD COLUMN IF NOT EXISTS source_system VARCHAR(32) NOT NULL DEFAULT 'bolt',
  ADD COLUMN IF NOT EXISTS source_endpoint VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS external_reference VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS payload_hash CHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS payload_json LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS raw_json LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS captured_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NULL;

ALTER TABLE normalized_bookings
  ADD COLUMN IF NOT EXISTS source_system VARCHAR(32) NOT NULL DEFAULT 'bolt',
  ADD COLUMN IF NOT EXISTS external_order_id VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS order_reference VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS source_trip_reference VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS driver_external_id VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS driver_name VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS vehicle_external_id VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS vehicle_plate VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS vehicle_model VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS passenger_name VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS lessee_name VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS pickup_address TEXT NULL,
  ADD COLUMN IF NOT EXISTS boarding_point TEXT NULL,
  ADD COLUMN IF NOT EXISTS destination_address TEXT NULL,
  ADD COLUMN IF NOT EXISTS disembark_point TEXT NULL,
  ADD COLUMN IF NOT EXISTS price VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS status VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS order_status VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS is_scheduled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS started_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS ended_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS order_created_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS raw_payload_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS normalized_payload_json LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS raw_payload_json LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS edxeix_payload_json LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS edxeix_ready TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL;

-- Suggested known mappings from verified live observations.
-- Run these after the sync if you want to seed the EDXEIX IDs immediately.
-- UPDATE mapping_drivers
-- SET edxeix_driver_id = 17585
-- WHERE source_system = 'bolt'
--   AND external_driver_id = '57256761-d21b-4940-a3ca-bdcec5ef6af1'
--   AND (edxeix_driver_id IS NULL OR edxeix_driver_id = 0);
--
-- UPDATE mapping_vehicles
-- SET edxeix_vehicle_id = 13799
-- WHERE source_system = 'bolt'
--   AND (external_vehicle_id = '3a008a4e-d81e-40ad-9414-8b4ef57d43e3' OR plate = 'EMX6874')
--   AND (edxeix_vehicle_id IS NULL OR edxeix_vehicle_id = 0);
--
-- UPDATE mapping_vehicles
-- SET edxeix_vehicle_id = 5949
-- WHERE source_system = 'bolt'
--   AND (external_vehicle_id = 'bd2c05a4-fab3-4329-865d-1170d9e6c997' OR plate = 'EHA2545')
--   AND (edxeix_vehicle_id IS NULL OR edxeix_vehicle_id = 0);
