-- gov.cabnet.app — Phase 66
-- Add lessor-specific EDXEIX starting point override for lessor 2307.
-- Verified before insert:
-- - mapping_lessor_starting_points had no active row for lessor 2307.
-- - mapping_starting_points had active edxeix_starting_point_id 6467495.
-- - driver 20999 belongs to lessor 2307.
-- - vehicle 13868 belongs to lessor 2307.
-- Safety: metadata/mapping only. Does not submit to EDXEIX, does not call AADE, does not change production pre-ride tool.

INSERT INTO mapping_lessor_starting_points
(
  edxeix_lessor_id,
  internal_key,
  label,
  edxeix_starting_point_id,
  is_active,
  updated_at
)
SELECT
  '2307',
  'edra_mas',
  label,
  edxeix_starting_point_id,
  1,
  NOW()
FROM mapping_starting_points
WHERE edxeix_starting_point_id = '6467495'
  AND is_active = 1
  AND NOT EXISTS (
    SELECT 1
    FROM mapping_lessor_starting_points
    WHERE edxeix_lessor_id = '2307'
      AND edxeix_starting_point_id = '6467495'
      AND is_active = 1
  )
LIMIT 1;

SELECT
  id,
  edxeix_lessor_id,
  internal_key,
  label,
  edxeix_starting_point_id,
  is_active,
  updated_at
FROM mapping_lessor_starting_points
WHERE edxeix_lessor_id = '2307'
ORDER BY id ASC;
