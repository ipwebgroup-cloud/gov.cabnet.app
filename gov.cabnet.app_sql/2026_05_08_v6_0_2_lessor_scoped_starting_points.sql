CREATE TABLE IF NOT EXISTS mapping_lessor_starting_points (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  edxeix_lessor_id BIGINT UNSIGNED NOT NULL,
  internal_key VARCHAR(100) NOT NULL,
  label VARCHAR(255) NOT NULL,
  edxeix_starting_point_id VARCHAR(50) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lessor_internal_key (edxeix_lessor_id, internal_key),
  KEY idx_lessor_starting_point_id (edxeix_lessor_id, edxeix_starting_point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mapping_lessor_starting_points
  (edxeix_lessor_id, internal_key, label, edxeix_starting_point_id, is_active)
VALUES
  (3814, 'edra_mas', 'ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα', '6467495', 1)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  edxeix_starting_point_id = VALUES(edxeix_starting_point_id),
  is_active = 1,
  updated_at = CURRENT_TIMESTAMP;
