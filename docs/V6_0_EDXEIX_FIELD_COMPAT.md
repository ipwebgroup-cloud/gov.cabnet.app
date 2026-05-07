# v6.0 — EDXEIX starting point field compatibility

## Purpose

EDXEIX browser inspection on 2026-05-08 showed that the start point dropdown is rendered as:

```html
<select id="starting_point" name="starting_point">
```

Older bridge code used `starting_point_id`. This patch keeps the old alias but also submits the current field name.

## Confirmed mapping

For lessor/partner `2124`:

- `6467495` = `ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα`

The `mapping_starting_points` row uses:

- `internal_key = edra_mas`
- `edxeix_starting_point_id = 6467495`

## Production safety

This patch does not enable automatic live EDXEIX submission.

The guarded live path remains one-shot/manual and still requires:

1. Real Bolt source.
2. Future start guard.
3. Driver mapping.
4. Vehicle mapping.
5. Starting point mapping.
6. No duplicate success.
7. One-shot booking lock.
8. Connected EDXEIX session.
9. Exact confirmation phrase.

## Important

Do not add a live submit cron yet.
