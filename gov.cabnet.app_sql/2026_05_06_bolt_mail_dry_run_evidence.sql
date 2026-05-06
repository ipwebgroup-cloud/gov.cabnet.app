-- gov.cabnet.app — v4.2 Bolt mail dry-run evidence
-- Additive table only. No submission jobs, no live submit path.

CREATE TABLE IF NOT EXISTS bolt_mail_dry_run_evidence (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  normalized_booking_id BIGINT UNSIGNED DEFAULT NULL,
  intake_id BIGINT UNSIGNED DEFAULT NULL,
  source VARCHAR(50) NOT NULL DEFAULT 'bolt_mail',
  evidence_status ENUM('recorded','blocked') NOT NULL DEFAULT 'recorded',
  payload_hash CHAR(64) NOT NULL,
  request_payload_json LONGTEXT NOT NULL,
  mapping_snapshot_json LONGTEXT DEFAULT NULL,
  safety_snapshot_json LONGTEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT 'ops',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bmdre_booking (normalized_booking_id),
  KEY idx_bmdre_intake (intake_id),
  KEY idx_bmdre_status (evidence_status),
  KEY idx_bmdre_created_at (created_at),
  KEY idx_bmdre_payload_hash (payload_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
