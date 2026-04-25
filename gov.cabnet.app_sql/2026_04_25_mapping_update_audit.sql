-- gov.cabnet.app — mapping editor audit table
-- Purpose: additive audit log for guarded manual EDXEIX ID mapping edits.
-- Safe to run more than once. No existing data is modified.

CREATE TABLE IF NOT EXISTS `mapping_update_audit` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` varchar(64) NOT NULL,
  `row_id` bigint(20) UNSIGNED NOT NULL,
  `field_name` varchar(64) NOT NULL,
  `old_value` varchar(191) DEFAULT NULL,
  `new_value` varchar(191) NOT NULL,
  `changed_by` varchar(191) DEFAULT NULL,
  `remote_ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mapping_update_audit_table_row` (`table_name`, `row_id`),
  KEY `idx_mapping_update_audit_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
