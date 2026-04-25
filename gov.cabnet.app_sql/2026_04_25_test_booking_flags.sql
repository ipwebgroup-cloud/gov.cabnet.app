-- gov.cabnet.app — optional LAB/local test booking safety flags
-- Date: 2026-04-25
-- Purpose:
--   Add explicit flags that identify synthetic dry-run rows and prevent any
--   future live-submit code from treating them as live-eligible bookings.
-- Safety:
--   Additive only. No existing data is deleted or changed.
-- Compatibility:
--   MariaDB 10.11 supports ADD COLUMN IF NOT EXISTS and ADD INDEX IF NOT EXISTS.

ALTER TABLE `normalized_bookings`
  ADD COLUMN IF NOT EXISTS `is_test_booking` tinyint(1) NOT NULL DEFAULT 0 AFTER `edxeix_ready`,
  ADD COLUMN IF NOT EXISTS `never_submit_live` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_test_booking`,
  ADD COLUMN IF NOT EXISTS `live_submit_block_reason` varchar(191) DEFAULT NULL AFTER `never_submit_live`;

ALTER TABLE `normalized_bookings`
  ADD INDEX IF NOT EXISTS `idx_normalized_lab_safety` (`source_system`, `is_test_booking`, `never_submit_live`, `started_at`);

ALTER TABLE `submission_jobs`
  ADD COLUMN IF NOT EXISTS `is_test_booking` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `never_submit_live` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_test_booking`,
  ADD COLUMN IF NOT EXISTS `live_submit_block_reason` varchar(191) DEFAULT NULL AFTER `never_submit_live`;

ALTER TABLE `submission_jobs`
  ADD INDEX IF NOT EXISTS `idx_jobs_lab_safety` (`status`, `is_test_booking`, `never_submit_live`);
