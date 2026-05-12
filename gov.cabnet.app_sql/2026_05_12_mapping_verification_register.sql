-- gov.cabnet.app — Mapping verification register
-- Additive migration only. Stores local verification decisions; no secrets.

CREATE TABLE IF NOT EXISTS mapping_verification_status (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  edxeix_lessor_id BIGINT UNSIGNED NOT NULL,
  lessor_name VARCHAR(255) NOT NULL DEFAULT '',
  verification_status VARCHAR(32) NOT NULL DEFAULT 'pending',
  starting_point_id VARCHAR(64) NOT NULL DEFAULT '',
  starting_point_label VARCHAR(255) NOT NULL DEFAULT '',
  source VARCHAR(64) NOT NULL DEFAULT 'manual_edxeix_ui',
  verified_by_user_id BIGINT UNSIGNED NULL,
  verified_by_name VARCHAR(190) NOT NULL DEFAULT '',
  verified_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mapping_verification_lessor (edxeix_lessor_id),
  KEY idx_mapping_verification_status (verification_status),
  KEY idx_mapping_verification_starting_point (starting_point_id),
  KEY idx_mapping_verification_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
