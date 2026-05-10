-- gov.cabnet.app
-- Bolt driver UUID to EDXEIX follow-up mapping sync
-- File: gov.cabnet.app_sql/2026_05_10_bolt_edxeix_driver_followup_sync.sql
--
-- Purpose:
-- Preserve live follow-up mappings completed after the EDXEIX browser export and Bolt UUID sync.
--
-- Safety:
-- - Update-only.
-- - No deletes.
-- - Does not contain credentials, tokens, cookies, sessions, or CSRF values.
-- - Does not submit anything to EDXEIX or AADE.
-- - Leaves unconfirmed drivers unmapped.

UPDATE mapping_drivers
SET edxeix_driver_id = 13343,
    edxeix_lessor_id = 4635,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND external_driver_name = 'Athina Karagiannidi'
  AND external_driver_id = 'b6f27118-85d6-4f43-8d79-232c2d58c604';

UPDATE mapping_drivers
SET edxeix_driver_id = 18799,
    edxeix_lessor_id = 2124,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND external_driver_name = 'Evangelos Karageorgos'
  AND external_driver_id = 'f7c61df4-9df2-4ed8-9230-20b802b25f21';

UPDATE mapping_drivers
SET edxeix_driver_id = 21581,
    edxeix_lessor_id = 2124,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND external_driver_name = 'Ioannis Kostopoulos'
  AND external_driver_id = 'd95e8caf-6878-43ad-b784-155212a23cf4';

UPDATE mapping_drivers
SET edxeix_driver_id = 10861,
    edxeix_lessor_id = 2124,
    is_active = 1,
    updated_at = NOW()
WHERE source_system = 'bolt'
  AND external_driver_name = 'Triantafyllos Tzantzaris'
  AND external_driver_id = '8caa966d-e34c-4775-8e0f-88789d5d4e0d';

-- Verification: newly confirmed follow-up mappings.
SELECT id, external_driver_name, external_driver_id, edxeix_driver_id, edxeix_lessor_id, is_active
FROM mapping_drivers
WHERE external_driver_name IN (
  'Athina Karagiannidi',
  'Evangelos Karageorgos',
  'Ioannis Kostopoulos',
  'Triantafyllos Tzantzaris'
)
ORDER BY external_driver_name;

-- Verification: active Bolt drivers still missing confirmed EDXEIX mapping.
SELECT id, external_driver_name, external_driver_id, edxeix_driver_id, edxeix_lessor_id, is_active
FROM mapping_drivers
WHERE is_active = 1
  AND external_driver_id IS NOT NULL
  AND (edxeix_driver_id IS NULL OR edxeix_lessor_id IS NULL)
ORDER BY external_driver_name;
