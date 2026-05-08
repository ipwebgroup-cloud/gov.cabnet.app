/*
  gov.cabnet.app v6.3.4
  Verified EDXEIX driver/vehicle mappings.

  Source:
  - Andreas-provided EDXEIX/Bolt driver ID list.
  - Andreas-provided EDXEIX portal vehicle option screenshots.

  Safety:
  - Exact Bolt driver UUID / exact plate updates only.
  - Does not create submission_jobs.
  - Does not create submission_attempts.
  - Does not call EDXEIX.
*/

START TRANSACTION;

/* Drivers */
UPDATE mapping_drivers SET edxeix_driver_id=293, updated_at=NOW()
WHERE driver_identifier='d02ecc46-03bc-407a-9fc6-4b1ed0b4e7e7';

UPDATE mapping_drivers SET edxeix_driver_id=21363, updated_at=NOW()
WHERE driver_identifier='480a48b4-ba42-4841-b797-611c6d8cfdcf';

UPDATE mapping_drivers SET edxeix_driver_id=17585, updated_at=NOW()
WHERE driver_identifier='57256761-d21b-4940-a3ca-bdcec5ef6af1';

UPDATE mapping_drivers SET edxeix_driver_id=20234, updated_at=NOW()
WHERE driver_identifier='03eb3b20-405b-4fdf-a54a-fcfe8d223920';

UPDATE mapping_drivers SET edxeix_driver_id=1658, updated_at=NOW()
WHERE driver_identifier='8364e9cc-fa7b-4af2-a330-99376e73d37d';

UPDATE mapping_drivers SET edxeix_driver_id=7702, updated_at=NOW()
WHERE driver_identifier='d245799d-b419-4edf-b8ff-272ac170f97c';

UPDATE mapping_drivers SET edxeix_driver_id=7703, updated_at=NOW()
WHERE driver_identifier='24dba5b8-861a-4428-9692-588217bf01b2';

UPDATE mapping_drivers SET edxeix_driver_id=17852, updated_at=NOW()
WHERE driver_identifier='74b6cc10-ef5f-41ef-8ccf-529729498deb';

UPDATE mapping_drivers SET edxeix_driver_id=20999, updated_at=NOW()
WHERE driver_identifier='11f67dab-2b61-45d9-8695-db373d9b21fb';

UPDATE mapping_drivers SET edxeix_driver_id=1303, updated_at=NOW()
WHERE driver_identifier='e68d3910-1178-4269-9064-f75a01c86a55';

UPDATE mapping_drivers SET edxeix_driver_id=12672, updated_at=NOW()
WHERE driver_identifier='53f25a2e-c267-4356-8e54-d55b005d27ac';

UPDATE mapping_drivers SET edxeix_driver_id=21249, updated_at=NOW()
WHERE driver_identifier='83ae689b-71c2-45d3-89df-5f0c6413accd';

UPDATE mapping_drivers SET edxeix_driver_id=13674, updated_at=NOW()
WHERE driver_identifier='cae07538-97e3-4f5f-b157-478a387d84f8';

UPDATE mapping_drivers SET edxeix_driver_id=4382, updated_at=NOW()
WHERE driver_identifier='8268654a-450a-4a0c-a3d4-933ad52bcacc';

UPDATE mapping_drivers SET edxeix_driver_id=19770, updated_at=NOW()
WHERE driver_identifier='75086d12-ef47-410e-8427-461460283286';

UPDATE mapping_drivers SET edxeix_driver_id=1031, updated_at=NOW()
WHERE driver_identifier='99fd463f-28d4-4fc2-b81e-c5ba59a081e8';

/* Vehicles */
UPDATE mapping_vehicles SET edxeix_vehicle_id=8955, updated_at=NOW()
WHERE UPPER(TRIM(plate))='EHA3174';

UPDATE mapping_vehicles SET edxeix_vehicle_id=11082, updated_at=NOW()
WHERE UPPER(TRIM(plate))='ZNN4655';

UPDATE mapping_vehicles SET edxeix_vehicle_id=2433, updated_at=NOW()
WHERE UPPER(TRIM(plate))='ITZ4966';

UPDATE mapping_vehicles SET edxeix_vehicle_id=5949, updated_at=NOW()
WHERE UPPER(TRIM(plate))='EHA2545';

UPDATE mapping_vehicles SET edxeix_vehicle_id=13799, updated_at=NOW()
WHERE UPPER(TRIM(plate))='EMX6874';

UPDATE mapping_vehicles SET edxeix_vehicle_id=11187, updated_at=NOW()
WHERE UPPER(TRIM(plate))='ITK7702';

UPDATE mapping_vehicles SET edxeix_vehicle_id=13868, updated_at=NOW()
WHERE UPPER(TRIM(plate))='XHT8172';

UPDATE mapping_vehicles SET edxeix_vehicle_id=9048, updated_at=NOW()
WHERE UPPER(TRIM(plate))='XHI9499';

UPDATE mapping_vehicles SET edxeix_vehicle_id=9049, updated_at=NOW()
WHERE UPPER(TRIM(plate))='XHK4448';

UPDATE mapping_vehicles SET edxeix_vehicle_id=13299, updated_at=NOW()
WHERE UPPER(TRIM(plate))='BKE7400';

UPDATE mapping_vehicles SET edxeix_vehicle_id=1084, updated_at=NOW()
WHERE UPPER(TRIM(plate))='EMT2299';

UPDATE mapping_vehicles SET edxeix_vehicle_id=251, updated_at=NOW()
WHERE UPPER(TRIM(plate))='KEZ7120';

UPDATE mapping_vehicles SET edxeix_vehicle_id=9396, updated_at=NOW()
WHERE UPPER(TRIM(plate))='XHI7105';

UPDATE mapping_vehicles SET edxeix_vehicle_id=14157, updated_at=NOW()
WHERE UPPER(TRIM(plate))='XHM6665';

UPDATE mapping_vehicles SET edxeix_vehicle_id=3528, updated_at=NOW()
WHERE UPPER(TRIM(plate))='ITX2334';

UPDATE mapping_vehicles SET edxeix_vehicle_id=4327, updated_at=NOW()
WHERE UPPER(TRIM(plate))='XZO1837';

UPDATE mapping_vehicles SET edxeix_vehicle_id=1641, updated_at=NOW()
WHERE UPPER(TRIM(plate))='IYB7366';

COMMIT;

SELECT
  'drivers_mapped' AS item,
  COUNT(*) AS count_value
FROM mapping_drivers
WHERE edxeix_driver_id IS NOT NULL AND edxeix_driver_id > 0
UNION ALL
SELECT
  'vehicles_mapped' AS item,
  COUNT(*) AS count_value
FROM mapping_vehicles
WHERE edxeix_vehicle_id IS NOT NULL AND edxeix_vehicle_id > 0
UNION ALL
SELECT
  'submission_jobs' AS item,
  COUNT(*) AS count_value
FROM submission_jobs
UNION ALL
SELECT
  'submission_attempts' AS item,
  COUNT(*) AS count_value
FROM submission_attempts;
