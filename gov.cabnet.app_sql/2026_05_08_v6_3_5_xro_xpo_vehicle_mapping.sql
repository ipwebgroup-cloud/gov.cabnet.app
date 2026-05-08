/*
  gov.cabnet.app v6.3.5
  Map Bolt-side XRO7604 row to verified EDXEIX vehicle XPO7604.

  Andreas confirmed:
  - XPO7604 is the correct plate in EDXEIX.
  - EDXEIX vehicle ID: 11390.

  Safety:
  - Updates mapping_vehicles only.
  - Does not call EDXEIX.
  - Does not create submission_jobs.
  - Does not create submission_attempts.
*/

UPDATE mapping_vehicles
SET
  edxeix_vehicle_id = 11390,
  updated_at = NOW()
WHERE UPPER(TRIM(plate)) = 'XRO7604';

SELECT
  id,
  plate,
  external_vehicle_name,
  vehicle_model,
  edxeix_vehicle_id,
  updated_at
FROM mapping_vehicles
WHERE UPPER(TRIM(plate)) IN ('XRO7604','XPO7604');

SELECT 'submission_jobs' AS item, COUNT(*) AS count_value FROM submission_jobs
UNION ALL
SELECT 'submission_attempts' AS item, COUNT(*) AS count_value FROM submission_attempts;
