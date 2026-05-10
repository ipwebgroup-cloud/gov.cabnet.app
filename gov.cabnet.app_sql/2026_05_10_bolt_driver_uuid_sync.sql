-- gov.cabnet.app
-- Bolt driver UUID sync
-- File: gov.cabnet.app_sql/2026_05_10_bolt_driver_uuid_sync.sql
--
-- Purpose:
-- Sync Bolt driver UUIDs into mapping_drivers.external_driver_id.
--
-- Safety:
-- - Additive/update-only.
-- - No deletes.
-- - Does not alter EDXEIX driver IDs or lessor IDs.
-- - Does not reactivate inactive duplicates.
-- - Inserts missing Bolt driver names with EDXEIX fields left NULL for later mapping.

CREATE TEMPORARY TABLE tmp_bolt_driver_uuid_sync (
  external_driver_name VARCHAR(255) NOT NULL,
  external_driver_id VARCHAR(191) NOT NULL,
  PRIMARY KEY (external_driver_name)
) ENGINE=Memory;

INSERT INTO tmp_bolt_driver_uuid_sync (external_driver_name, external_driver_id) VALUES
('Stefanos Kaci','11f67dab-2b61-45d9-8695-db373d9b21fb'),
('Theofylaktos Angelidis','d245799d-b419-4edf-b8ff-272ac170f97c'),
('Christos Tselepidis','24dba5b8-861a-4428-9692-588217bf01b2'),
('Efthymios Giakis','74b6cc10-ef5f-41ef-8ccf-529729498deb'),
('Nikolaos Kastanias','d02ecc46-03bc-407a-9fc6-4b1ed0b4e7e7'),
('Alexios Alexakis','53f25a2e-c267-4356-8e54-d55b005d27ac'),
('Apostolos Nestoridis','480a48b4-ba42-4841-b797-611c6d8cfdcf'),
('Athina Karagiannidi','b6f27118-85d6-4f43-8d79-232c2d58c604'),
('Chrysovalantis Tanaskos','09613c80-d09c-44b9-8df0-0bec5fc8dca8'),
('Dimitrios Amasiadis','cb7801f4-801a-4865-a5d4-faa5d89ae9b3'),
('Evangelos Grendas','97d33913-6ce9-4b2b-8f7f-f5a2945db97d'),
('Evangelos Karageorgos','f7c61df4-9df2-4ed8-9230-20b802b25f21'),
('Filippos Giannakopoulos','57256761-d21b-4940-a3ca-bdcec5ef6af1'),
('Georgios Kallinteris','03eb3b20-405b-4fdf-a54a-fcfe8d223920'),
('Georgios Simos','83ae689b-71c2-45d3-89df-5f0c6413accd'),
('Georgios Tsatsas','8268654a-450a-4a0c-a3d4-933ad52bcacc'),
('Georgios Zachariou','e68d3910-1178-4269-9064-f75a01c86a55'),
('Gerasimos Kontogeorgis','d88682e6-6f4c-4f72-a688-5bebef1ff7db'),
('Ioannis Fanteev','307ad4fc-e091-450f-89b4-2275ef039cc9'),
('Ioannis Kostopoulos','d95e8caf-6878-43ad-b784-155212a23cf4'),
('Ioannis Kounter','5365edef-d657-4515-866f-7ef06700092f'),
('Ioannis Stavrakis','75086d12-ef47-410e-8427-461460283286'),
('Ioannis Zeris','99fd463f-28d4-4fc2-b81e-c5ba59a081e8'),
('Iosif Manouselis','86228873-1294-4c74-8f78-48f3ec0d6cbd'),
('Lampros Kanellos','922e8436-fb54-4e9d-9be3-767997b77a75'),
('Markos Kontos','761dbee6-b82e-44e9-99d7-f55fbb70d598'),
('Nikolaos Skotidas','cae07538-97e3-4f5f-b157-478a387d84f8'),
('Nikolaos Vidakis','8364e9cc-fa7b-4af2-a330-99376e73d37d'),
('Qaisar Abdul-Moeed','acd6b8df-3eae-4cc7-af52-b593563352a6'),
('Toutsis Konstantinos','52aa5cbb-742a-44f2-a9b3-eff01b4e2e41'),
('Triantafyllos Tzantzaris','8caa966d-e34c-4775-8e0f-88789d5d4e0d');

-- Update existing rows by exact Bolt/display driver name.
UPDATE mapping_drivers md
JOIN tmp_bolt_driver_uuid_sync t
  ON md.source_system = 'bolt'
 AND md.external_driver_name = t.external_driver_name
SET md.external_driver_id = t.external_driver_id,
    md.updated_at = NOW();

-- Insert missing names only. EDXEIX IDs/company stay NULL until confirmed by EDXEIX export/mapping.
INSERT INTO mapping_drivers
(source_system, external_driver_id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active, created_at, updated_at)
SELECT 'bolt', t.external_driver_id, t.external_driver_name, NULL, NULL, 1, NOW(), NOW()
FROM tmp_bolt_driver_uuid_sync t
WHERE NOT EXISTS (
    SELECT 1
    FROM mapping_drivers md
    WHERE md.source_system = 'bolt'
      AND md.external_driver_name = t.external_driver_name
);

-- Verification: show synced rows.
SELECT md.id,
       md.source_system,
       md.external_driver_name,
       md.external_driver_id,
       md.edxeix_driver_id,
       md.edxeix_lessor_id,
       md.is_active
FROM mapping_drivers md
WHERE md.source_system = 'bolt'
  AND md.external_driver_name IN (
    'Stefanos Kaci',
    'Theofylaktos Angelidis',
    'Christos Tselepidis',
    'Efthymios Giakis',
    'Nikolaos Kastanias',
    'Alexios Alexakis',
    'Apostolos Nestoridis',
    'Athina Karagiannidi',
    'Chrysovalantis Tanaskos',
    'Dimitrios Amasiadis',
    'Evangelos Grendas',
    'Evangelos Karageorgos',
    'Filippos Giannakopoulos',
    'Georgios Kallinteris',
    'Georgios Simos',
    'Georgios Tsatsas',
    'Georgios Zachariou',
    'Gerasimos Kontogeorgis',
    'Ioannis Fanteev',
    'Ioannis Kostopoulos',
    'Ioannis Kounter',
    'Ioannis Stavrakis',
    'Ioannis Zeris',
    'Iosif Manouselis',
    'Lampros Kanellos',
    'Markos Kontos',
    'Nikolaos Skotidas',
    'Nikolaos Vidakis',
    'Qaisar Abdul-Moeed',
    'Toutsis Konstantinos',
    'Triantafyllos Tzantzaris'
  )
ORDER BY md.external_driver_name, md.id;

-- Verification: show active rows still missing EDXEIX IDs/company after UUID sync.
SELECT md.id,
       md.external_driver_name,
       md.external_driver_id,
       md.edxeix_driver_id,
       md.edxeix_lessor_id,
       md.is_active
FROM mapping_drivers md
WHERE md.source_system = 'bolt'
  AND md.is_active = 1
  AND md.external_driver_name IN (
    'Stefanos Kaci',
    'Theofylaktos Angelidis',
    'Christos Tselepidis',
    'Efthymios Giakis',
    'Nikolaos Kastanias',
    'Alexios Alexakis',
    'Apostolos Nestoridis',
    'Athina Karagiannidi',
    'Chrysovalantis Tanaskos',
    'Dimitrios Amasiadis',
    'Evangelos Grendas',
    'Evangelos Karageorgos',
    'Filippos Giannakopoulos',
    'Georgios Kallinteris',
    'Georgios Simos',
    'Georgios Tsatsas',
    'Georgios Zachariou',
    'Gerasimos Kontogeorgis',
    'Ioannis Fanteev',
    'Ioannis Kostopoulos',
    'Ioannis Kounter',
    'Ioannis Stavrakis',
    'Ioannis Zeris',
    'Iosif Manouselis',
    'Lampros Kanellos',
    'Markos Kontos',
    'Nikolaos Skotidas',
    'Nikolaos Vidakis',
    'Qaisar Abdul-Moeed',
    'Toutsis Konstantinos',
    'Triantafyllos Tzantzaris'
  )
  AND (md.edxeix_driver_id IS NULL OR md.edxeix_lessor_id IS NULL)
ORDER BY md.external_driver_name, md.id;
