-- gov.cabnet.app Bolt dedupe/cleanup compatibility fix
-- Safe scope: fixes legacy empty hashes/defaults and removes known placeholder demo rows.
-- No EDXEIX submission is performed by this SQL.

SET NAMES utf8mb4;

-- If these columns exist, make empty hashes unique per row instead of leaving them as ''.
-- Run table/column-specific statements only where the column exists on your schema.

UPDATE `bolt_raw_payloads`
SET `dedupe_hash` = SHA2(CONCAT('bolt_raw_payloads|', COALESCE(NULLIF(`payload_hash`, ''), ''), '|', COALESCE(`external_reference`, ''), '|', `id`), 256)
WHERE `dedupe_hash` IS NULL OR `dedupe_hash` = '';

UPDATE `bolt_raw_payloads`
SET `payload_hash` = SHA2(CONCAT('payload|', COALESCE(`external_reference`, ''), '|', `id`), 256)
WHERE `payload_hash` IS NULL OR `payload_hash` = '';

UPDATE `normalized_bookings`
SET `dedupe_hash` = SHA2(CONCAT('normalized_bookings|', COALESCE(`source_system`, 'bolt'), '|', COALESCE(NULLIF(`external_order_id`, ''), NULLIF(`order_reference`, ''), `id`), '|', `id`), 256)
WHERE `dedupe_hash` IS NULL OR `dedupe_hash` = '';

-- Do not compare DECIMAL price to ''. Strict MariaDB may throw truncated DECIMAL errors.
UPDATE `normalized_bookings`
SET `price` = 0
WHERE `price` IS NULL;

DELETE FROM `normalized_bookings`
WHERE `driver_external_id` = 'drv-test-001'
   OR `vehicle_external_id` = 'veh-test-001'
   OR `external_order_id` IN ('test-booking-001','manual-test-001','drv-test-001')
   OR `order_reference` IN ('test-booking-001','manual-test-001','drv-test-001');

DELETE FROM `mapping_drivers`
WHERE `external_driver_id` = 'drv-test-001';

DELETE FROM `mapping_vehicles`
WHERE `external_vehicle_id` = 'veh-test-001'
   OR (`plate` = 'EHA2545' AND `source_system` = 'manual');
