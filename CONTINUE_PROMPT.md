Continue the gov.cabnet.app Bolt → EDXEIX / AADE bridge from v6.0.

AADE work is paused. Focus is now guarded EDXEIX live-production readiness.

Current v6.0 detail:

- EDXEIX browser form uses `name="starting_point"` for `Σημείο έναρξης`.
- Bridge should populate both `starting_point` and `starting_point_id` with the same value for compatibility.
- Confirmed default mapping: `edra_mas` → `6467495` = `ΕΔΡΑ ΜΑΣ`.
- SQL seed file: `/home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_0_edxeix_starting_point_6467495.sql`.

Safety posture:

- Do not enable automatic EDXEIX live submit cron.
- Use only one-shot guarded manual submit.
- Live submit remains blocked unless a real eligible future Bolt booking passes analysis, one-shot lock is set, EDXEIX session is connected, and the exact confirmation phrase is supplied.

Next commands after deploying v6.0:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixFormReader.php
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixPayloadBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php

DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"] ?? $c["database"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_0_edxeix_starting_point_6467495.sql

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=BOOKING_ID --analyze-only
```

In analyze-only output, verify:

- `edxeix_payload_preview.starting_point = 6467495`
- `edxeix_payload_preview.starting_point_id = 6467495`
- only acceptable blockers before session connection are `edxeix_session_not_connected`, `edxeix_session_not_ready`, and possibly `one_shot_live_lock_missing`.
