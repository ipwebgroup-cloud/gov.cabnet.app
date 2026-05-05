-- gov.cabnet.app — Bolt Mail Intake → Preflight Candidate Bridge v3.7
-- Additive migration only.
-- Purpose: speed up lookup of intake rows linked to normalized bookings.
-- Safe to run more than once.

SET @idx_exists := (
    SELECT COUNT(1)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bolt_mail_intake'
      AND INDEX_NAME = 'idx_bolt_mail_intake_linked_booking_id'
);

SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE bolt_mail_intake ADD KEY idx_bolt_mail_intake_linked_booking_id (linked_booking_id)',
    'SELECT "idx_bolt_mail_intake_linked_booking_id already exists" AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
