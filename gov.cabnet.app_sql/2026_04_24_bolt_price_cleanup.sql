-- gov.cabnet.app Bolt price normalization + starter placeholder cleanup
-- Safe to run more than once.
-- No EDXEIX submission is performed by this SQL.

UPDATE `normalized_bookings`
SET `price` = 0
WHERE `price` IS NULL OR `price` = '';

DELETE FROM `normalized_bookings`
WHERE `driver_external_id` = 'drv-test-001'
   OR `vehicle_external_id` = 'veh-test-001'
   OR `external_order_id` IN ('test-booking-001','manual-test-001','drv-test-001')
   OR `order_reference` IN ('test-booking-001','manual-test-001','drv-test-001');

DELETE FROM `mapping_drivers`
WHERE `external_driver_id` = 'drv-test-001'
   OR `driver_uuid` = 'drv-test-001'
   OR `external_id` = 'drv-test-001';

DELETE FROM `mapping_vehicles`
WHERE `external_vehicle_id` = 'veh-test-001'
   OR `vehicle_uuid` = 'veh-test-001'
   OR `external_id` = 'veh-test-001';
