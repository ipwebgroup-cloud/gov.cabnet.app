-- gov.cabnet.app
-- EDXEIX browser export snapshot and safe mapping sync
-- File: gov.cabnet.app_sql/2026_05_10_edxeix_browser_export_sync.sql
--
-- Source: read-only browser export from EDXEIX create form dropdowns.
-- Export generated_at: 2026-05-10T16:56:44.629Z
-- Export source_url: https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307
--
-- Safety:
-- - Additive/update-only.
-- - No deletes.
-- - Does not contain credentials, cookies, sessions, tokens, or CSRF values.
-- - Stores EDXEIX IDs in local snapshot tables for review/audit.
-- - Updates operational vehicle mappings by plate because vehicle plates are stable.
-- - Updates operational driver lessor only for unambiguous EDXEIX driver IDs or explicit Bolt UUID aliases.
-- - Does not submit anything to EDXEIX.


CREATE TABLE IF NOT EXISTS edxeix_export_lessors (
  lessor_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  lessor_label VARCHAR(255) NOT NULL,
  driver_count INT UNSIGNED NOT NULL DEFAULT 0,
  vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
  starting_point_count INT UNSIGNED NOT NULL DEFAULT 0,
  export_generated_at DATETIME NULL,
  source_url VARCHAR(500) NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edxeix_export_drivers (
  lessor_id BIGINT UNSIGNED NOT NULL,
  edxeix_driver_id BIGINT UNSIGNED NOT NULL,
  driver_label VARCHAR(255) NOT NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (lessor_id, edxeix_driver_id),
  KEY idx_edxeix_driver_id (edxeix_driver_id),
  KEY idx_driver_label (driver_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edxeix_export_vehicles (
  lessor_id BIGINT UNSIGNED NOT NULL,
  edxeix_vehicle_id BIGINT UNSIGNED NOT NULL,
  vehicle_label VARCHAR(255) NOT NULL,
  plate_norm VARCHAR(50) NOT NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (lessor_id, edxeix_vehicle_id),
  KEY idx_edxeix_vehicle_id (edxeix_vehicle_id),
  KEY idx_plate_norm (plate_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edxeix_export_starting_points (
  lessor_id BIGINT UNSIGNED NOT NULL,
  edxeix_starting_point_id BIGINT UNSIGNED NOT NULL,
  starting_point_label TEXT NOT NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (lessor_id, edxeix_starting_point_id),
  KEY idx_edxeix_starting_point_id (edxeix_starting_point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_driver_id;

ALTER TABLE mapping_vehicles
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_vehicle_id;


-- Snapshot: lessors

INSERT INTO edxeix_export_lessors
(lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at)
VALUES (2124, 'N G K ΜΟΝΟΠΡΟΣΩΠΗ Ι Κ Ε', 5, 3, 4, STR_TO_DATE('2026-05-10 16:56:44', '%Y-%m-%d %H:%i:%s'), 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307', NOW(), NOW())
ON DUPLICATE KEY UPDATE
lessor_label = VALUES(lessor_label),
driver_count = VALUES(driver_count),
vehicle_count = VALUES(vehicle_count),
starting_point_count = VALUES(starting_point_count),
export_generated_at = VALUES(export_generated_at),
source_url = VALUES(source_url),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_lessors
(lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at)
VALUES (3814, 'LUXLIMO Ι Κ Ε', 4, 2, 1, STR_TO_DATE('2026-05-10 16:56:44', '%Y-%m-%d %H:%i:%s'), 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307', NOW(), NOW())
ON DUPLICATE KEY UPDATE
lessor_label = VALUES(lessor_label),
driver_count = VALUES(driver_count),
vehicle_count = VALUES(vehicle_count),
starting_point_count = VALUES(starting_point_count),
export_generated_at = VALUES(export_generated_at),
source_url = VALUES(source_url),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_lessors
(lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at)
VALUES (2307, 'QUALITATIVE TRANSFER MYKONOS ΙΚ Ε', 4, 2, 2, STR_TO_DATE('2026-05-10 16:56:44', '%Y-%m-%d %H:%i:%s'), 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307', NOW(), NOW())
ON DUPLICATE KEY UPDATE
lessor_label = VALUES(lessor_label),
driver_count = VALUES(driver_count),
vehicle_count = VALUES(vehicle_count),
starting_point_count = VALUES(starting_point_count),
export_generated_at = VALUES(export_generated_at),
source_url = VALUES(source_url),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_lessors
(lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at)
VALUES (3894, 'ΜΥΚΟΝΟΣ TOURIST AGENCY ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ', 2, 3, 2, STR_TO_DATE('2026-05-10 16:56:44', '%Y-%m-%d %H:%i:%s'), 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307', NOW(), NOW())
ON DUPLICATE KEY UPDATE
lessor_label = VALUES(lessor_label),
driver_count = VALUES(driver_count),
vehicle_count = VALUES(vehicle_count),
starting_point_count = VALUES(starting_point_count),
export_generated_at = VALUES(export_generated_at),
source_url = VALUES(source_url),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_lessors
(lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at)
VALUES (1487, 'VIP ROAD MYKONOS ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ', 6, 9, 1, STR_TO_DATE('2026-05-10 16:56:44', '%Y-%m-%d %H:%i:%s'), 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307', NOW(), NOW())
ON DUPLICATE KEY UPDATE
lessor_label = VALUES(lessor_label),
driver_count = VALUES(driver_count),
vehicle_count = VALUES(vehicle_count),
starting_point_count = VALUES(starting_point_count),
export_generated_at = VALUES(export_generated_at),
source_url = VALUES(source_url),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_lessors
(lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at)
VALUES (1756, 'WHITEBLUE PREMIUM Ε Ε', 9, 2, 2, STR_TO_DATE('2026-05-10 16:56:44', '%Y-%m-%d %H:%i:%s'), 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307', NOW(), NOW())
ON DUPLICATE KEY UPDATE
lessor_label = VALUES(lessor_label),
driver_count = VALUES(driver_count),
vehicle_count = VALUES(vehicle_count),
starting_point_count = VALUES(starting_point_count),
export_generated_at = VALUES(export_generated_at),
source_url = VALUES(source_url),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_lessors
(lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at)
VALUES (4635, 'LUX MYKONOS Ο Ε', 2, 1, 1, STR_TO_DATE('2026-05-10 16:56:44', '%Y-%m-%d %H:%i:%s'), 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307', NOW(), NOW())
ON DUPLICATE KEY UPDATE
lessor_label = VALUES(lessor_label),
driver_count = VALUES(driver_count),
vehicle_count = VALUES(vehicle_count),
starting_point_count = VALUES(starting_point_count),
export_generated_at = VALUES(export_generated_at),
source_url = VALUES(source_url),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_lessors
(lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at)
VALUES (3474, 'ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ', 1, 1, 2, STR_TO_DATE('2026-05-10 16:56:44', '%Y-%m-%d %H:%i:%s'), 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=2307', NOW(), NOW())
ON DUPLICATE KEY UPDATE
lessor_label = VALUES(lessor_label),
driver_count = VALUES(driver_count),
vehicle_count = VALUES(vehicle_count),
starting_point_count = VALUES(starting_point_count),
export_generated_at = VALUES(export_generated_at),
source_url = VALUES(source_url),
last_seen_at = NOW(),
updated_at = NOW();


-- Snapshot: drivers

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2124, 18799, 'ΚΑΡΑΓΕΩΡΓΟΣ ΕΥΑΓΓΕΛΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2124, 293, 'ΚΑΣΤΑΝΙΑΣ ΝΙΚΟΛΑΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2124, 21581, 'ΚΩΣΤΟΠΟΥΛΟΣ ΙΩΑΝΝΗΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2124, 21363, 'ΝΕΣΤΟΡΙΔΗΣ ΑΠΟΣΤΟΛΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2124, 10861, 'ΤΖΑΝΤΖΑΡΗΣ ΤΡΙΑΝΤΑΦΥΛΛΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (3814, 1658, 'ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (3814, 17585, 'ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΛΙΠΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (3814, 20234, 'ΚΑΛΛΙΝΤΕΡΗΣ ΓΕΩΡΓΙΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (3814, 6026, 'ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2307, 20999, 'KACI STEFANOS', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2307, 7702, 'ΑΓΓΕΛΙΔΗΣ ΘΕΟΦΥΛΑΚΤΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2307, 17852, 'ΓΙΑΚΗΣ ΕΥΘΥΜΙΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (2307, 7703, 'ΤΣΕΛΕΠΙΔΗΣ ΧΡΗΣΤΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (3894, 1303, 'ΖΑΧΑΡΙΟΥ ΓΕΩΡΓΙΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (3894, 21657, 'ΚΑΝΕΛΛΟΣ ΛΑΜΠΡΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1487, 12672, 'ΑΛΕΞΑΚΗΣ ΑΛΕΞΙΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1487, 4459, 'ΑΣΠΙΩΤΗΣ ΚΥΡΙΑΚΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1487, 11042, 'ΚΟΝΤΟΣ ΜΑΡΚΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1487, 6498, 'ΜΠΕΧΤΣΟΠΟΥΛΟΣ ΠΑΝΑΓΙΩΤΗΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1487, 21249, 'ΣΙΜΟΣ ΓΕΩΡΓΙΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1487, 13674, 'ΣΚΟΤΙΔΑΣ ΝΙΚΟΛΑΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 1031, 'ΖΕΡΗΣ ΙΩΑΝΝΗΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 20635, 'ΚΑΨΑΛΗΣ ΠΑΝΑΓΙΩΤΗΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 20258, 'ΚΟΚΛΑΝΗΣ ΒΑΣΙΛΕΙΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 3973, 'ΚΟΥΛΟΥΡΗΣ ΠΕΤΡΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 20018, 'ΜΠΟΥΓΙΟΥΚ-ΒΕΡΒΕΡΟΓΛΟΥ ΠΑΝΑΓΙΩΤΗΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 21638, 'ΝΙΝΟΣ ΓΕΩΡΓΙΟΣ ΝΙΚΟΛ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 20208, 'ΠΑΠΑΔΑΚΗΣ ΕΜΜΑΝΟΥΗΛ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 19770, 'ΣΤΑΥΡΑΚΗΣ ΙΩΑΝΝΗΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (1756, 4382, 'ΤΣΑΤΣΑΣ ΓΕΩΡΓΙΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (4635, 13343, 'ΚΑΡΑΓΙΑΝΝΙΔΗ ΑΘΗΝΑ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (4635, 4171, 'ΚΟΝΤΟΓΕΩΡΓΗΣ ΓΕΡΑΣΙΜΟΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_drivers
(lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at)
VALUES (3474, 6026, 'ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
driver_label = VALUES(driver_label),
last_seen_at = NOW(),
updated_at = NOW();


-- Snapshot: vehicles

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (2124, 8955, 'ΕΗΑ3174', 'EHA3174', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (2124, 11082, 'ΖΝΝ4655', 'ZNN4655', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (2124, 2433, 'ΙΤΖ4966', 'ITZ4966', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (3814, 5949, 'ΕΗΑ2545', 'EHA2545', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (3814, 13799, 'ΕΜΧ6874', 'EMX6874', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (2307, 11187, 'ΙΤΚ7702', 'ITK7702', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (2307, 13868, 'ΧΗΤ8172', 'XHT8172', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (3894, 9048, 'ΧΗΙ9499', 'XHI9499', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (3894, 9049, 'ΧΗΚ4448', 'XHK4448', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (3894, 11390, 'ΧΡΟ7604', 'XPO7604', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 13299, 'ΒΚΕ7400', 'BKE7400', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 1084, 'ΕΜΤ2299', 'EMT2299', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 251, 'ΚΕΖ7120', 'KEZ7120', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 12905, 'ΝΚΝ7684', 'NKN7684', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 9319, 'ΧΖΙ7481', 'XZI7481', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 9396, 'ΧΗΙ7105', 'XHI7105', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 14157, 'ΧΗΜ6665', 'XHM6665', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 12911, 'ΧΡΚ5054', 'XPK5054', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1487, 14014, 'ΧΡΤ8889', 'XPT8889', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1756, 3528, 'ΙΤΧ2334', 'ITX2334', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (1756, 4327, 'ΧΖΟ1837', 'XZO1837', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (4635, 1641, 'ΙΥΒ7366', 'IYB7366', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_vehicles
(lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at)
VALUES (3474, 9384, 'ΡΕΡ7858', 'PEP7858', NOW(), NOW())
ON DUPLICATE KEY UPDATE
vehicle_label = VALUES(vehicle_label),
plate_norm = VALUES(plate_norm),
last_seen_at = NOW(),
updated_at = NOW();


-- Snapshot: starting points

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (2124, 1431181, 'Κοινότητα Άνω Μεράς, Άνω Μερά, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (2124, 6467495, 'ΕΔΡΑ ΜΑΣ , Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (2124, 9349292, 'LAKONIAS 17 PIREAUS', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (2124, 9349293, 'LAKONIAS 17 PIREAUS', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (3814, 5875309, 'Έδρα μας', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (2307, 1455969, 'ΧΩΡΑ ΜΥΚΟΝΟΥ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (2307, 9700559, 'ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (3894, 5902522, 'Κοινότητα Μυκονίων, Κλουβάς, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (3894, 5902524, 'Νέου Φαλήρου, 31, Σμολένσκυ, Νέο Φάληρο, 3η Κοινότητα Πειραιά, Πειραιάς, Δήμος Πειραιώς, Περιφερειακή Ενότητα Πειραιώς, Περιφέρεια Αττικής, Αποκεντρωμένη Διοίκηση Αττικής, 185 47, Ελλάδα', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (1487, 408867, 'Δραφάκι, Κοινότητα Μυκονίων, Mykonos, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 84600, Ελλάς', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (1756, 464343, 'Λεωφόρος Μεσογείων 15', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (1756, 612164, 'Ομβροδέκτης, Κοινότητα Μυκονίων, Mykonos, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 84600, Ελλάδα', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (4635, 9486633, 'Παραδείσια, Κοινότητα Μυκονίων, Άγιος Στέφανος, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (3474, 3785995, 'ΜΙΣΣΙΡΙΑ, ΡΕΘΥΜΝΟ, ΡΕΘΥΜΝΗΣ, ΚΡΗΤΗ', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();

INSERT INTO edxeix_export_starting_points
(lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at)
VALUES (3474, 9761676, 'Κοινότητα Μυκονίων, Κλουβάς, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα', NOW(), NOW())
ON DUPLICATE KEY UPDATE
starting_point_label = VALUES(starting_point_label),
last_seen_at = NOW(),
updated_at = NOW();


-- Operational mapping sync: vehicles by normalized plate.

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 8955,
    edxeix_lessor_id = 2124,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΕΗΑ3174'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'EHA3174'
   OR edxeix_vehicle_id = 8955;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'EHA3174', 8955, 2124, 'ΕΗΑ3174', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'EHA3174'
       OR edxeix_vehicle_id = 8955
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 11082,
    edxeix_lessor_id = 2124,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΖΝΝ4655'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'ZNN4655'
   OR edxeix_vehicle_id = 11082;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'ZNN4655', 11082, 2124, 'ΖΝΝ4655', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'ZNN4655'
       OR edxeix_vehicle_id = 11082
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 2433,
    edxeix_lessor_id = 2124,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΙΤΖ4966'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'ITZ4966'
   OR edxeix_vehicle_id = 2433;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'ITZ4966', 2433, 2124, 'ΙΤΖ4966', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'ITZ4966'
       OR edxeix_vehicle_id = 2433
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 5949,
    edxeix_lessor_id = 3814,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΕΗΑ2545'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'EHA2545'
   OR edxeix_vehicle_id = 5949;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'EHA2545', 5949, 3814, 'ΕΗΑ2545', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'EHA2545'
       OR edxeix_vehicle_id = 5949
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 13799,
    edxeix_lessor_id = 3814,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΕΜΧ6874'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'EMX6874'
   OR edxeix_vehicle_id = 13799;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'EMX6874', 13799, 3814, 'ΕΜΧ6874', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'EMX6874'
       OR edxeix_vehicle_id = 13799
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 11187,
    edxeix_lessor_id = 2307,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΙΤΚ7702'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'ITK7702'
   OR edxeix_vehicle_id = 11187;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'ITK7702', 11187, 2307, 'ΙΤΚ7702', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'ITK7702'
       OR edxeix_vehicle_id = 11187
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 13868,
    edxeix_lessor_id = 2307,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΗΤ8172'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XHT8172'
   OR edxeix_vehicle_id = 13868;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XHT8172', 13868, 2307, 'ΧΗΤ8172', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XHT8172'
       OR edxeix_vehicle_id = 13868
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 9048,
    edxeix_lessor_id = 3894,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΗΙ9499'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XHI9499'
   OR edxeix_vehicle_id = 9048;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XHI9499', 9048, 3894, 'ΧΗΙ9499', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XHI9499'
       OR edxeix_vehicle_id = 9048
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 9049,
    edxeix_lessor_id = 3894,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΗΚ4448'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XHK4448'
   OR edxeix_vehicle_id = 9049;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XHK4448', 9049, 3894, 'ΧΗΚ4448', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XHK4448'
       OR edxeix_vehicle_id = 9049
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 11390,
    edxeix_lessor_id = 3894,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΡΟ7604'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XPO7604'
   OR edxeix_vehicle_id = 11390;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XPO7604', 11390, 3894, 'ΧΡΟ7604', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XPO7604'
       OR edxeix_vehicle_id = 11390
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 13299,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΒΚΕ7400'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'BKE7400'
   OR edxeix_vehicle_id = 13299;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'BKE7400', 13299, 1487, 'ΒΚΕ7400', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'BKE7400'
       OR edxeix_vehicle_id = 13299
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 1084,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΕΜΤ2299'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'EMT2299'
   OR edxeix_vehicle_id = 1084;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'EMT2299', 1084, 1487, 'ΕΜΤ2299', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'EMT2299'
       OR edxeix_vehicle_id = 1084
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 251,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΚΕΖ7120'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'KEZ7120'
   OR edxeix_vehicle_id = 251;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'KEZ7120', 251, 1487, 'ΚΕΖ7120', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'KEZ7120'
       OR edxeix_vehicle_id = 251
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 12905,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΝΚΝ7684'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'NKN7684'
   OR edxeix_vehicle_id = 12905;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'NKN7684', 12905, 1487, 'ΝΚΝ7684', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'NKN7684'
       OR edxeix_vehicle_id = 12905
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 9319,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΖΙ7481'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XZI7481'
   OR edxeix_vehicle_id = 9319;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XZI7481', 9319, 1487, 'ΧΖΙ7481', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XZI7481'
       OR edxeix_vehicle_id = 9319
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 9396,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΗΙ7105'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XHI7105'
   OR edxeix_vehicle_id = 9396;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XHI7105', 9396, 1487, 'ΧΗΙ7105', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XHI7105'
       OR edxeix_vehicle_id = 9396
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 14157,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΗΜ6665'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XHM6665'
   OR edxeix_vehicle_id = 14157;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XHM6665', 14157, 1487, 'ΧΗΜ6665', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XHM6665'
       OR edxeix_vehicle_id = 14157
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 12911,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΡΚ5054'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XPK5054'
   OR edxeix_vehicle_id = 12911;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XPK5054', 12911, 1487, 'ΧΡΚ5054', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XPK5054'
       OR edxeix_vehicle_id = 12911
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 14014,
    edxeix_lessor_id = 1487,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΡΤ8889'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XPT8889'
   OR edxeix_vehicle_id = 14014;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XPT8889', 14014, 1487, 'ΧΡΤ8889', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XPT8889'
       OR edxeix_vehicle_id = 14014
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 3528,
    edxeix_lessor_id = 1756,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΙΤΧ2334'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'ITX2334'
   OR edxeix_vehicle_id = 3528;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'ITX2334', 3528, 1756, 'ΙΤΧ2334', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'ITX2334'
       OR edxeix_vehicle_id = 3528
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 4327,
    edxeix_lessor_id = 1756,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΧΖΟ1837'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'XZO1837'
   OR edxeix_vehicle_id = 4327;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'XZO1837', 4327, 1756, 'ΧΖΟ1837', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'XZO1837'
       OR edxeix_vehicle_id = 4327
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 1641,
    edxeix_lessor_id = 4635,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΙΥΒ7366'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'IYB7366'
   OR edxeix_vehicle_id = 1641;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'IYB7366', 1641, 4635, 'ΙΥΒ7366', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'IYB7366'
       OR edxeix_vehicle_id = 1641
);

UPDATE mapping_vehicles
SET edxeix_vehicle_id = 9384,
    edxeix_lessor_id = 3474,
    external_vehicle_name = COALESCE(NULLIF(external_vehicle_name, ''), 'ΡΕΡ7858'),
    is_active = 1,
    updated_at = NOW()
WHERE REPLACE(UPPER(plate),' ','') = 'PEP7858'
   OR edxeix_vehicle_id = 9384;

INSERT INTO mapping_vehicles
(source_system, external_vehicle_id, plate, edxeix_vehicle_id, edxeix_lessor_id, external_vehicle_name, is_active, created_at, updated_at)
SELECT 'edxeix', NULL, 'PEP7858', 9384, 3474, 'ΡΕΡ7858', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_vehicles
    WHERE REPLACE(UPPER(plate),' ','') = 'PEP7858'
       OR edxeix_vehicle_id = 9384
);


-- Operational mapping sync: existing drivers with unambiguous EDXEIX driver IDs only.

UPDATE mapping_drivers
SET edxeix_lessor_id = 1487,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 4459
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1487);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1487,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 6498
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1487);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1487,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 11042
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1487);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1487,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 12672
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1487);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1487,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 13674
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1487);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1487,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 21249
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1487);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 1031
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 3973
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 4382
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 19770
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 20018
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 20208
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 20258
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 20635
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 1756,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 21638
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 1756);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2124,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 293
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2124);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2124,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 10861
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2124);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2124,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 18799
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2124);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2124,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 21363
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2124);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2124,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 21581
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2124);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2307,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 7702
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2307);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2307,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 7703
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2307);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2307,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 17852
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2307);

UPDATE mapping_drivers
SET edxeix_lessor_id = 2307,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 20999
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 2307);

UPDATE mapping_drivers
SET edxeix_lessor_id = 3814,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 1658
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 3814);

UPDATE mapping_drivers
SET edxeix_lessor_id = 3814,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 17585
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 3814);

UPDATE mapping_drivers
SET edxeix_lessor_id = 3814,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 20234
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 3814);

UPDATE mapping_drivers
SET edxeix_lessor_id = 3894,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 1303
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 3894);

UPDATE mapping_drivers
SET edxeix_lessor_id = 3894,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 21657
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 3894);

UPDATE mapping_drivers
SET edxeix_lessor_id = 4635,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 4171
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 4635);

UPDATE mapping_drivers
SET edxeix_lessor_id = 4635,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id = 13343
  AND (edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0 OR edxeix_lessor_id = 4635);


-- Explicit Bolt UUID aliases supplied/confirmed by operations.

-- ΜΥΚΟΝΟΣ TOURIST AGENCY / MTA / Partner ID 3894
-- Lampros Kanellos / KANELLOS LAMPROS
UPDATE mapping_drivers
SET external_driver_id = '922e8436-fb54-4e9d-9be3-767997b77a75',
    edxeix_driver_id = 21657,
    edxeix_lessor_id = 3894,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND (
      external_driver_name = 'KANELLOS LAMPROS'
      OR external_driver_name = 'Lampros Kanellos'
      OR edxeix_driver_id = 21657
      OR external_driver_id = '922e8436-fb54-4e9d-9be3-767997b77a75'
  );

INSERT INTO mapping_drivers
(source_system, external_driver_id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active, created_at, updated_at)
SELECT 'bolt', '922e8436-fb54-4e9d-9be3-767997b77a75', 'Lampros Kanellos', 21657, 3894, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_drivers
    WHERE external_driver_id = '922e8436-fb54-4e9d-9be3-767997b77a75'
       OR external_driver_name IN ('KANELLOS LAMPROS', 'Lampros Kanellos')
);

-- LUX MYKONOS / Partner ID 4635
-- KONTOGEORGOS GERASIMOS
UPDATE mapping_drivers
SET external_driver_id = 'd88682e6-6f4c-4f72-a688-5bebef1ff7db',
    edxeix_driver_id = 4171,
    edxeix_lessor_id = 4635,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND (
      external_driver_name = 'KONTOGEORGOS GERASIMOS'
      OR external_driver_name = 'Gerasimos Kontogeorgis'
      OR external_driver_name = 'Gerasimos Kontogeorgos'
      OR edxeix_driver_id = 4171
      OR external_driver_id = 'd88682e6-6f4c-4f72-a688-5bebef1ff7db'
  );

INSERT INTO mapping_drivers
(source_system, external_driver_id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active, created_at, updated_at)
SELECT 'bolt', 'd88682e6-6f4c-4f72-a688-5bebef1ff7db', 'KONTOGEORGOS GERASIMOS', 4171, 4635, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_drivers
    WHERE external_driver_id = 'd88682e6-6f4c-4f72-a688-5bebef1ff7db'
       OR external_driver_name IN ('KONTOGEORGOS GERASIMOS', 'Gerasimos Kontogeorgis', 'Gerasimos Kontogeorgos')
);

-- MANOUSELIS SIFIS / Partner ID 3474
-- Do not globally override every driver row with edxeix_driver_id=6026 because EDXEIX also lists 6026 under LUXLIMO.
UPDATE mapping_drivers
SET external_driver_id = '86228873-1294-4c74-8f78-48f3ec0d6cbd',
    edxeix_driver_id = 6026,
    edxeix_lessor_id = 3474,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND (
      external_driver_name = 'MAOUSELIS SIFIS'
      OR external_driver_name = 'MANOUSELIS SIFIS'
      OR external_driver_name = 'Manouselis Sifis'
      OR external_driver_id = '86228873-1294-4c74-8f78-48f3ec0d6cbd'
  );

INSERT INTO mapping_drivers
(source_system, external_driver_id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active, created_at, updated_at)
SELECT 'bolt', '86228873-1294-4c74-8f78-48f3ec0d6cbd', 'MANOUSELIS SIFIS', 6026, 3474, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM mapping_drivers
    WHERE external_driver_id = '86228873-1294-4c74-8f78-48f3ec0d6cbd'
       OR external_driver_name IN ('MAOUSELIS SIFIS', 'MANOUSELIS SIFIS', 'Manouselis Sifis')
);

-- Keep known duplicate Filippos mapping inactive.
UPDATE mapping_drivers
SET is_active = 0,
    updated_at = NOW()
WHERE id = 31
  AND external_driver_name = 'Filippos Giannakopoulos'
  AND edxeix_driver_id = 17585;


-- Verification queries.
SELECT lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count
FROM edxeix_export_lessors
ORDER BY lessor_id;

SELECT id, source_system, external_driver_id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active
FROM mapping_drivers
WHERE edxeix_driver_id IN (17852, 20999, 17585, 20234, 1658, 6026, 21657, 4171)
   OR external_driver_id IN (
       '922e8436-fb54-4e9d-9be3-767997b77a75',
       'd88682e6-6f4c-4f72-a688-5bebef1ff7db',
       '86228873-1294-4c74-8f78-48f3ec0d6cbd'
   )
ORDER BY edxeix_lessor_id, external_driver_name, id;

SELECT id, source_system, plate, edxeix_vehicle_id, edxeix_lessor_id, is_active
FROM mapping_vehicles
WHERE edxeix_vehicle_id IS NOT NULL
ORDER BY edxeix_lessor_id, plate, id;

-- Ambiguous EDXEIX driver IDs from this export:
-- 6026 appears under lessors 3814 and 3474. The explicit Bolt alias above maps MANOUSELIS SIFIS to 3474.
