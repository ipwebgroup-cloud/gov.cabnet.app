-- gov.cabnet.app — V3 verified EDXEIX starting-point options
-- Additive, V3-only safety table.
-- Purpose: guard V3 queue rows from using starting_point_id values that are not available on the EDXEIX lessor form.

CREATE TABLE IF NOT EXISTS pre_ride_email_v3_starting_point_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  edxeix_lessor_id VARCHAR(64) NOT NULL,
  edxeix_starting_point_id VARCHAR(64) NOT NULL,
  label VARCHAR(190) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  source VARCHAR(80) NOT NULL DEFAULT 'operator_verified',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_v3_start_option_lessor_point (edxeix_lessor_id, edxeix_starting_point_id),
  KEY idx_v3_start_option_lessor_active (edxeix_lessor_id, is_active),
  KEY idx_v3_start_option_point (edxeix_starting_point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verified from the live EDXEIX lessor=2307 form during V3 testing.
INSERT INTO pre_ride_email_v3_starting_point_options
(edxeix_lessor_id, edxeix_starting_point_id, label, is_active, source, notes)
VALUES
('2307', '1455969', 'ΧΩΡΑ ΜΥΚΟΝΟΥ', 1, 'operator_verified_edxeix_form', 'Observed in EDXEIX lessor=2307 starting-point dropdown.'),
('2307', '9700559', 'ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ', 1, 'operator_verified_edxeix_form', 'Observed in EDXEIX lessor=2307 starting-point dropdown.')
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  is_active = VALUES(is_active),
  source = VALUES(source),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;
