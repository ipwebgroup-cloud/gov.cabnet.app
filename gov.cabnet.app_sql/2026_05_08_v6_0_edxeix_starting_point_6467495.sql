-- gov.cabnet.app — v6.0 EDXEIX starting point mapping seed
-- Safe/idempotent: updates the existing edra_mas mapping or inserts it if missing.
-- Confirmed from EDXEIX create form for lessor 2124:
--   6467495 = ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα

INSERT INTO mapping_starting_points (
    internal_key,
    label,
    edxeix_starting_point_id,
    is_active,
    created_at,
    updated_at
) VALUES (
    'edra_mas',
    'ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα',
    '6467495',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    edxeix_starting_point_id = VALUES(edxeix_starting_point_id),
    is_active = 1,
    updated_at = NOW();
