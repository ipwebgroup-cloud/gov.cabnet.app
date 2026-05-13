-- gov.cabnet.app — V3 live-submit operator approval table
-- Safe additive migration. No production submission tables are touched.

CREATE TABLE IF NOT EXISTS pre_ride_email_v3_live_submit_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  queue_id BIGINT UNSIGNED NOT NULL,
  dedupe_key VARCHAR(80) NOT NULL,
  approval_status VARCHAR(40) NOT NULL DEFAULT 'approved',
  approval_scope VARCHAR(80) NOT NULL DEFAULT 'single_row_live_submit_handoff',
  approved_by VARCHAR(120) NOT NULL DEFAULT 'operator_web',
  approved_at DATETIME NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  approval_note TEXT NULL,
  row_snapshot_json LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prv3_live_submit_approvals_queue (queue_id),
  KEY idx_prv3_live_submit_approvals_dedupe (dedupe_key),
  KEY idx_prv3_live_submit_approvals_status_expiry (approval_status, expires_at),
  KEY idx_prv3_live_submit_approvals_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
