-- gov.cabnet.app — WHITEBLUE EDXEIX starting point override
-- Safe additive/refresh migration. Does not change global starting point defaults.
-- Live EDXEIX confirmed:
--   Lessor/company: WHITEBLUE PREMIUM E E / 1756
--   Starting point: Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600
--   EDXEIX starting_point_id: 612164

START TRANSACTION;

INSERT INTO mapping_lessor_starting_points
  (
    edxeix_lessor_id,
    internal_key,
    label,
    edxeix_starting_point_id,
    is_active,
    created_at,
    updated_at
  )
SELECT
    1756,
    'whiteblue_default',
    'WHITEBLUE / Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600',
    '612164',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM mapping_lessor_starting_points
    WHERE edxeix_lessor_id = 1756
      AND edxeix_starting_point_id = '612164'
);

UPDATE mapping_lessor_starting_points
SET
  internal_key = 'whiteblue_default',
  label = 'WHITEBLUE / Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600',
  is_active = 1,
  updated_at = NOW()
WHERE edxeix_lessor_id = 1756
  AND edxeix_starting_point_id = '612164';

COMMIT;

SELECT
  id,
  edxeix_lessor_id,
  internal_key,
  label,
  edxeix_starting_point_id,
  is_active,
  created_at,
  updated_at
FROM mapping_lessor_starting_points
WHERE edxeix_lessor_id = 1756
ORDER BY id ASC;
