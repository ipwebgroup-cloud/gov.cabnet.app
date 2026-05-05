-- gov.cabnet.app — Bolt pre-ride mail intake
-- Additive migration only. No destructive changes.
-- Live EDXEIX submission remains disabled.

CREATE TABLE IF NOT EXISTS bolt_mail_intake (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_mailbox VARCHAR(190) NOT NULL DEFAULT 'bolt-bridge@gov.cabnet.app',
  source_path VARCHAR(500) DEFAULT NULL,
  source_basename VARCHAR(255) DEFAULT NULL,
  message_id VARCHAR(255) DEFAULT NULL,
  message_hash CHAR(64) NOT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  sender_email VARCHAR(190) DEFAULT NULL,
  received_at DATETIME DEFAULT NULL,

  operator_raw VARCHAR(255) DEFAULT NULL,
  customer_name VARCHAR(190) DEFAULT NULL,
  customer_mobile VARCHAR(80) DEFAULT NULL,
  driver_name VARCHAR(190) DEFAULT NULL,
  vehicle_plate VARCHAR(40) DEFAULT NULL,
  pickup_address TEXT DEFAULT NULL,
  dropoff_address TEXT DEFAULT NULL,

  start_time_raw VARCHAR(80) DEFAULT NULL,
  estimated_pickup_time_raw VARCHAR(80) DEFAULT NULL,
  estimated_end_time_raw VARCHAR(80) DEFAULT NULL,
  estimated_price_raw VARCHAR(80) DEFAULT NULL,

  parsed_start_at DATETIME DEFAULT NULL,
  parsed_pickup_at DATETIME DEFAULT NULL,
  parsed_end_at DATETIME DEFAULT NULL,
  timezone_label VARCHAR(20) DEFAULT NULL,

  parse_status ENUM('parsed','needs_review','rejected') NOT NULL DEFAULT 'needs_review',
  safety_status ENUM('future_candidate','blocked_past','blocked_too_soon','needs_review') NOT NULL DEFAULT 'needs_review',
  rejection_reason TEXT DEFAULT NULL,

  linked_booking_id BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_bolt_mail_intake_hash (message_hash),
  KEY idx_bolt_mail_intake_status (parse_status, safety_status),
  KEY idx_bolt_mail_intake_pickup (parsed_pickup_at),
  KEY idx_bolt_mail_intake_vehicle (vehicle_plate),
  KEY idx_bolt_mail_intake_driver (driver_name),
  KEY idx_bolt_mail_intake_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
