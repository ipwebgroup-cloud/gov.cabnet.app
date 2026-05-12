-- gov.cabnet.app — Mobile Submit Evidence Log
-- Additive table for sanitized mobile/server-side EDXEIX submit dry-run evidence.
-- Stores sanitized evidence JSON only. Raw email bodies, cookies, sessions, token values,
-- credentials, and real config values must not be stored here.

CREATE TABLE IF NOT EXISTS mobile_submit_evidence_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evidence_sha256 CHAR(64) NOT NULL,
  email_sha256 CHAR(64) DEFAULT NULL,
  source_label VARCHAR(190) DEFAULT NULL,
  final_status VARCHAR(80) DEFAULT NULL,
  lessor_id VARCHAR(32) DEFAULT NULL,
  driver_id VARCHAR(32) DEFAULT NULL,
  vehicle_id VARCHAR(32) DEFAULT NULL,
  starting_point_id VARCHAR(32) DEFAULT NULL,
  evidence_json LONGTEXT NOT NULL,
  notes TEXT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_by_username VARCHAR(190) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mobile_submit_evidence_sha256 (evidence_sha256),
  KEY idx_mobile_submit_evidence_created_at (created_at),
  KEY idx_mobile_submit_evidence_status (final_status),
  KEY idx_mobile_submit_evidence_lessor (lessor_id),
  KEY idx_mobile_submit_evidence_driver_vehicle (driver_id, vehicle_id),
  KEY idx_mobile_submit_evidence_starting_point (starting_point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
