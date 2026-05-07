# HANDOFF — gov.cabnet.app Bolt → EDXEIX / AADE Bridge v6.0

Current phase:

- AADE development work is paused.
- EDXEIX bridge is moving into guarded production-launch readiness.
- Live EDXEIX submission must remain manual and one-shot only until explicitly approved after a successful controlled production test.

v6.0 change:

- Browser inspection showed the EDXEIX start point dropdown uses `name="starting_point"`, not only `starting_point_id`.
- The bridge now supports both `starting_point` and `starting_point_id` for compatibility.
- Confirmed start point mapping:
  - `edra_mas` → `6467495`
  - Label: `ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα`

Files changed:

- `/home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixFormReader.php`
- `/home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixPayloadBuilder.php`
- `/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php`
- `/home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_0_edxeix_starting_point_6467495.sql`
- `/home/cabnet/docs/V6_0_EDXEIX_FIELD_COMPAT.md` if docs are mirrored outside the app package, otherwise repository `docs/`.

After deploy:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixFormReader.php
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixPayloadBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php

DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"] ?? $c["database"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_0_edxeix_starting_point_6467495.sql
mysql "$DB_NAME" -e "SELECT internal_key,label,edxeix_starting_point_id,is_active FROM mapping_starting_points WHERE internal_key='edra_mas';"
```

Next safe production step:

1. Arm live-submit session-disconnected mode if not already armed.
2. Select one real future Bolt booking.
3. Run analyze-only.
4. Confirm payload contains `starting_point=6467495` and `starting_point_id=6467495`.
5. Set one-shot lock.
6. Connect fresh EDXEIX session.
7. Run analyze-only again.
8. Submit one booking manually with the exact phrase only if all blockers are cleared.

Never submit historical, cancelled, expired, synthetic, lab, duplicate, or past bookings.
