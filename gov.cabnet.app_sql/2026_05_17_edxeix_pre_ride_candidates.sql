-- gov.cabnet.app — v3.2.22 additive pre-ride EDXEIX candidate table
-- Safety: additive only. Does not alter production V0 tables, queues, AADE, or normalized_bookings.
-- Raw pre-ride email body is intentionally not stored.

CREATE TABLE IF NOT EXISTS edxeix_pre_ride_candidates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_hash CHAR(64) NOT NULL,
  source_type VARCHAR(80) NOT NULL DEFAULT '',
  source_label VARCHAR(255) NOT NULL DEFAULT '',
  source_mtime VARCHAR(32) NOT NULL DEFAULT '',
  order_reference VARCHAR(190) NOT NULL DEFAULT '',
  pickup_datetime DATETIME NULL,
  estimated_end_datetime DATETIME NULL,
  customer_name VARCHAR(190) NOT NULL DEFAULT '',
  customer_phone VARCHAR(80) NOT NULL DEFAULT '',
  driver_name VARCHAR(190) NOT NULL DEFAULT '',
  vehicle_plate VARCHAR(32) NOT NULL DEFAULT '',
  pickup_address VARCHAR(500) NOT NULL DEFAULT '',
  dropoff_address VARCHAR(500) NOT NULL DEFAULT '',
  price_amount VARCHAR(40) NOT NULL DEFAULT '',
  price_currency VARCHAR(12) NOT NULL DEFAULT '',
  status ENUM('ready','blocked','archived') NOT NULL DEFAULT 'blocked',
  readiness_status VARCHAR(80) NOT NULL DEFAULT 'BLOCKED_PRE_RIDE_CANDIDATE',
  ready_for_edxeix TINYINT(1) NOT NULL DEFAULT 0,
  parsed_fields_json JSON NULL,
  payload_preview_json JSON NULL,
  mapping_status_json JSON NULL,
  safety_blockers_json JSON NULL,
  warnings_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_edxeix_pre_ride_candidates_source_hash (source_hash),
  KEY idx_edxeix_pre_ride_candidates_pickup (pickup_datetime),
  KEY idx_edxeix_pre_ride_candidates_ready (ready_for_edxeix, status),
  KEY idx_edxeix_pre_ride_candidates_plate (vehicle_plate),
  KEY idx_edxeix_pre_ride_candidates_order_ref (order_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
