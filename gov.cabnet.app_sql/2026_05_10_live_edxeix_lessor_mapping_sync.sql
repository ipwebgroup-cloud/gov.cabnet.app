-- gov.cabnet.app
-- Live EDXEIX lessor mapping sync
-- File: gov.cabnet.app_sql/2026_05_10_live_edxeix_lessor_mapping_sync.sql
--
-- Purpose:
-- Preserve the current production mapping state used by the manual Bolt pre-ride
-- email -> EDXEIX workflow.
--
-- Safety:
-- - Additive/update-only.
-- - No deletes.
-- - Does not contain credentials, tokens, cookies, or private config.
-- - Driver and vehicle mappings remain independent.
-- - EDXEIX company/lessor ownership is treated as source of truth.

ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_driver_id;

ALTER TABLE mapping_vehicles
  ADD COLUMN IF NOT EXISTS edxeix_lessor_id BIGINT UNSIGNED DEFAULT NULL AFTER edxeix_vehicle_id;

-- QUALITATIVE TRANSFER MYKONOS ΙΚ Ε / Partner ID 2307
UPDATE mapping_drivers
SET edxeix_lessor_id = 2307,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id IN (17852, 20999);

UPDATE mapping_vehicles
SET edxeix_lessor_id = 2307,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_vehicle_id IN (11187, 13868)
   OR REPLACE(UPPER(plate),' ','') IN ('ITK7702','XHT8172','ΧΗΤ8172');

-- LUXLIMO Ι Κ Ε / Partner ID 3814
UPDATE mapping_drivers
SET edxeix_lessor_id = 3814,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_driver_id IN (17585, 20234, 1658, 6026);

UPDATE mapping_vehicles
SET edxeix_lessor_id = 3814,
    is_active = 1,
    updated_at = NOW()
WHERE edxeix_vehicle_id IN (5949, 13799)
   OR REPLACE(UPPER(plate),' ','') IN ('EHA2545','EMX6874');

-- ΜΥΚΟΝΟΣ TOURIST AGENCY / MTA / Partner ID 3894
-- Lampros Kanellos / KANELLOS LAMPROS
-- Bolt driver UUID: 922e8436-fb54-4e9d-9be3-767997b77a75
-- EDXEIX driver ID: 21657
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
SELECT 'bolt',
       '922e8436-fb54-4e9d-9be3-767997b77a75',
       'Lampros Kanellos',
       21657,
       3894,
       1,
       NOW(),
       NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM mapping_drivers
    WHERE source_system = 'bolt'
      AND (
          external_driver_name = 'KANELLOS LAMPROS'
          OR external_driver_name = 'Lampros Kanellos'
          OR edxeix_driver_id = 21657
          OR external_driver_id = '922e8436-fb54-4e9d-9be3-767997b77a75'
      )
);

-- Keep duplicate Filippos mapping inactive.
UPDATE mapping_drivers
SET is_active = 0,
    updated_at = NOW()
WHERE id = 31
  AND external_driver_name = 'Filippos Giannakopoulos'
  AND edxeix_driver_id = 17585;

-- Verification queries.
SELECT id, external_driver_name, external_driver_id, edxeix_driver_id, edxeix_lessor_id, is_active
FROM mapping_drivers
WHERE edxeix_driver_id IN (17852, 20999, 17585, 20234, 1658, 6026, 21657)
   OR external_driver_id = '922e8436-fb54-4e9d-9be3-767997b77a75'
ORDER BY edxeix_lessor_id, external_driver_name, id;

SELECT id, plate, edxeix_vehicle_id, edxeix_lessor_id, is_active
FROM mapping_vehicles
WHERE edxeix_vehicle_id IN (11187, 13868, 5949, 13799)
   OR REPLACE(UPPER(plate),' ','') IN ('ITK7702','XHT8172','ΧΗΤ8172','EHA2545','EMX6874')
ORDER BY edxeix_lessor_id, plate, id;
