-- gov.cabnet.app — v5.1 Bolt mail driver receipt copy audit columns
-- Additive migration only. No destructive changes.
-- Adds receipt-copy tracking to the existing driver notification audit table.

ALTER TABLE bolt_mail_driver_notifications
  ADD COLUMN IF NOT EXISTS receipt_subject VARCHAR(255) DEFAULT NULL AFTER email_subject,
  ADD COLUMN IF NOT EXISTS receipt_status ENUM('sent','skipped','failed') NOT NULL DEFAULT 'skipped' AFTER notification_status,
  ADD COLUMN IF NOT EXISTS receipt_skip_reason VARCHAR(191) DEFAULT NULL AFTER skip_reason,
  ADD COLUMN IF NOT EXISTS receipt_error_message TEXT DEFAULT NULL AFTER error_message,
  ADD COLUMN IF NOT EXISTS receipt_sent_at DATETIME DEFAULT NULL AFTER sent_at,
  ADD COLUMN IF NOT EXISTS receipt_vat_rate DECIMAL(5,2) NOT NULL DEFAULT 13.00 AFTER receipt_sent_at,
  ADD COLUMN IF NOT EXISTS receipt_total_amount DECIMAL(10,2) DEFAULT NULL AFTER receipt_vat_rate,
  ADD COLUMN IF NOT EXISTS receipt_net_amount DECIMAL(10,2) DEFAULT NULL AFTER receipt_total_amount,
  ADD COLUMN IF NOT EXISTS receipt_vat_amount DECIMAL(10,2) DEFAULT NULL AFTER receipt_net_amount;
