-- gov.cabnet.app starter schema
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `cabnet_gov`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `cabnet_gov`;

DROP TABLE IF EXISTS submission_attempts;
DROP TABLE IF EXISTS submission_jobs;
DROP TABLE IF EXISTS mapping_starting_points;
DROP TABLE IF EXISTS mapping_vehicles;
DROP TABLE IF EXISTS mapping_drivers;
DROP TABLE IF EXISTS normalized_bookings;
DROP TABLE IF EXISTS bolt_raw_payloads;

CREATE TABLE bolt_raw_payloads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_type VARCHAR(50) NOT NULL,
  source_id VARCHAR(191) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  fetched_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_source_id (source_id),
  KEY idx_source_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE normalized_bookings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source VARCHAR(50) NOT NULL,
  source_trip_id VARCHAR(191) NULL,
  source_booking_id VARCHAR(191) NULL,
  status VARCHAR(50) NOT NULL,
  customer_type VARCHAR(20) NOT NULL,
  customer_name VARCHAR(255) NOT NULL,
  customer_vat_number VARCHAR(50) NULL,
  customer_representative VARCHAR(255) NULL,
  driver_external_id VARCHAR(191) NULL,
  driver_name VARCHAR(255) NULL,
  vehicle_external_id VARCHAR(191) NULL,
  vehicle_plate VARCHAR(50) NULL,
  starting_point_key VARCHAR(100) NULL,
  boarding_point TEXT NOT NULL,
  coordinates VARCHAR(255) NULL,
  disembark_point TEXT NOT NULL,
  drafted_at DATETIME NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
  broker_key VARCHAR(100) NULL,
  notes TEXT NULL,
  dedupe_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dedupe_hash (dedupe_hash),
  KEY idx_source_trip_id (source_trip_id),
  KEY idx_started_at (started_at),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mapping_drivers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_system VARCHAR(50) NOT NULL DEFAULT 'bolt',
  external_driver_id VARCHAR(191) NULL,
  external_driver_name VARCHAR(255) NOT NULL,
  edxeix_driver_id VARCHAR(50) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_external_driver_id (external_driver_id),
  KEY idx_external_driver_name (external_driver_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mapping_vehicles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_system VARCHAR(50) NOT NULL DEFAULT 'bolt',
  external_vehicle_id VARCHAR(191) NULL,
  plate VARCHAR(50) NOT NULL,
  edxeix_vehicle_id VARCHAR(50) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_external_vehicle_id (external_vehicle_id),
  KEY idx_plate (plate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mapping_starting_points (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  internal_key VARCHAR(100) NOT NULL,
  label VARCHAR(255) NOT NULL,
  edxeix_starting_point_id VARCHAR(50) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_internal_key (internal_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submission_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  normalized_booking_id BIGINT UNSIGNED NOT NULL,
  target_system VARCHAR(50) NOT NULL DEFAULT 'edxeix',
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  priority INT NOT NULL DEFAULT 100,
  available_at DATETIME NOT NULL,
  locked_at DATETIME NULL,
  locked_by VARCHAR(100) NULL,
  last_error TEXT NULL,
  retry_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_status_available (status, available_at),
  KEY idx_booking_id (normalized_booking_id),
  CONSTRAINT fk_submission_jobs_booking FOREIGN KEY (normalized_booking_id)
    REFERENCES normalized_bookings(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submission_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_job_id BIGINT UNSIGNED NOT NULL,
  request_payload_json LONGTEXT NOT NULL,
  response_status INT NULL,
  response_headers_json LONGTEXT NULL,
  response_body LONGTEXT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  remote_reference VARCHAR(191) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_submission_job_id (submission_job_id),
  CONSTRAINT fk_submission_attempts_job FOREIGN KEY (submission_job_id)
    REFERENCES submission_jobs(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mapping_starting_points (internal_key, label, edxeix_starting_point_id, is_active)
VALUES ('edra_mas', 'Έδρα μας', '5875309', 1);

SET FOREIGN_KEY_CHECKS = 1;
