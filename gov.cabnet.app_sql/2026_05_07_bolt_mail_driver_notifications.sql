-- gov.cabnet.app — v4.5 Bolt mail driver notification audit
-- Additive migration only. No destructive changes.
-- Stores one idempotent notification outcome per imported Bolt pre-ride email.

CREATE TABLE IF NOT EXISTS bolt_mail_driver_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  intake_id BIGINT UNSIGNED NOT NULL,
  message_hash CHAR(64) DEFAULT NULL,
  driver_name VARCHAR(190) DEFAULT NULL,
  vehicle_plate VARCHAR(40) DEFAULT NULL,
  recipient_email VARCHAR(190) DEFAULT NULL,
  email_subject VARCHAR(255) DEFAULT NULL,
  notification_status ENUM('sent','skipped','failed') NOT NULL DEFAULT 'skipped',
  skip_reason VARCHAR(191) DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  mail_transport VARCHAR(50) NOT NULL DEFAULT 'php_mail',
  sent_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_bolt_mail_driver_notifications_intake (intake_id),
  KEY idx_bolt_mail_driver_notifications_status (notification_status, created_at),
  KEY idx_bolt_mail_driver_notifications_driver (driver_name),
  KEY idx_bolt_mail_driver_notifications_plate (vehicle_plate),
  KEY idx_bolt_mail_driver_notifications_recipient (recipient_email),
  KEY idx_bolt_mail_driver_notifications_hash (message_hash),
  CONSTRAINT fk_bolt_mail_driver_notifications_intake
    FOREIGN KEY (intake_id) REFERENCES bolt_mail_intake(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
