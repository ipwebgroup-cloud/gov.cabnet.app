-- gov.cabnet.app — EDXEIX live submission audit table
-- Additive migration only. Does not alter existing data.

CREATE TABLE IF NOT EXISTS `edxeix_live_submission_audit` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `normalized_booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_reference` varchar(191) DEFAULT NULL,
  `source_system` varchar(50) DEFAULT NULL,
  `payload_hash` char(64) DEFAULT NULL,
  `request_payload_json` longtext DEFAULT NULL,
  `response_status` int(11) DEFAULT 0,
  `response_body` longtext DEFAULT NULL,
  `response_json` longtext DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `remote_reference` varchar(191) DEFAULT NULL,
  `mode` varchar(80) NOT NULL DEFAULT 'live_submit_gate',
  `live_blockers_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`normalized_booking_id`),
  KEY `idx_order_reference` (`order_reference`),
  KEY `idx_payload_hash` (`payload_hash`),
  KEY `idx_success_created` (`success`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
