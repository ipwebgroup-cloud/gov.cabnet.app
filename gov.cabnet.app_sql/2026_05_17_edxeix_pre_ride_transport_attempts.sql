-- gov.cabnet.app — optional sanitized EDXEIX pre-ride transport attempts table
-- v3.2.30
-- Additive only. Stores no raw cookie, CSRF token, or raw response body.

CREATE TABLE IF NOT EXISTS `edxeix_pre_ride_transport_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `candidate_id` BIGINT UNSIGNED NULL,
  `transport_id` VARCHAR(128) NOT NULL DEFAULT '',
  `rehearsal_id` VARCHAR(128) NOT NULL DEFAULT '',
  `source_hash_16` VARCHAR(32) NOT NULL DEFAULT '',
  `payload_hash` CHAR(64) NOT NULL DEFAULT '',
  `classification_code` VARCHAR(160) NOT NULL DEFAULT '',
  `classification_message` TEXT NULL,
  `transport_requested` TINYINT(1) NOT NULL DEFAULT 0,
  `transport_performed` TINYINT(1) NOT NULL DEFAULT 0,
  `first_http_status` INT NOT NULL DEFAULT 0,
  `final_http_status` INT NOT NULL DEFAULT 0,
  `step_count` INT NOT NULL DEFAULT 0,
  `trace_json` LONGTEXT NULL,
  `blockers_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_candidate_id` (`candidate_id`),
  KEY `idx_payload_hash` (`payload_hash`),
  KEY `idx_transport_id` (`transport_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
