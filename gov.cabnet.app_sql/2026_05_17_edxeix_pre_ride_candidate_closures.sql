-- gov.cabnet.app — v3.2.31 additive pre-ride candidate closure table
-- Safety: additive only. Does not alter V0 production, AADE, queues, or normalized_bookings.
-- Purpose: record manual V0/laptop submission proof and block server-side retry/duplicate attempts.

CREATE TABLE IF NOT EXISTS edxeix_pre_ride_candidate_closures (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidate_id BIGINT UNSIGNED NOT NULL,
  source_hash CHAR(64) NOT NULL DEFAULT '',
  payload_hash CHAR(64) NOT NULL DEFAULT '',
  closure_status ENUM('manual_submitted_v0','server_not_confirmed','do_not_retry') NOT NULL DEFAULT 'manual_submitted_v0',
  method VARCHAR(80) NOT NULL DEFAULT 'v0_laptop_manual',
  submitted_by VARCHAR(190) NOT NULL DEFAULT '',
  submitted_at DATETIME NULL,
  note VARCHAR(1000) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_edxeix_pre_ride_candidate_closures_candidate (candidate_id),
  KEY idx_edxeix_pre_ride_candidate_closures_source_hash (source_hash),
  KEY idx_edxeix_pre_ride_candidate_closures_payload_hash (payload_hash),
  KEY idx_edxeix_pre_ride_candidate_closures_status (closure_status),
  KEY idx_edxeix_pre_ride_candidate_closures_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
