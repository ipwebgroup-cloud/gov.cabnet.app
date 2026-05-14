You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Continue V3 development only. Do not touch V0 laptop/manual production helper or its dependencies.

Current boundary:
- V0 = laptop/manual production helper.
- V3 = PC/server-side automation development path.
- Andreas uses operator judgment during live rides.
- No software should decide fallback to V0.
- Live EDXEIX submit remains disabled.

Latest operational finding:
- V3 pulse cron failed because `/home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock` was owned by `root:root`.
- It was repaired to `cabnet:cabnet` with mode `0660`.
- Manual tests of the pulse cron worker must run as `cabnet`, not root.

Latest patch:
- v3.0.40-pulse-lock-owner-hardening
- Updates V3 storage check CLI and Ops page to include pulse lock file owner/writability.
- Adds docs for V3 storage/pulse check and V0/V3 boundary.
- No SQL, no V0 changes, no live-submit changes.

Key URLs:
- https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
- https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
- https://gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
- https://gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php

Next safe V3 work:
- polish Queue Watch / Pulse Monitor / Automation Readiness using the same Ops shell style,
- keep visibility fast and simple,
- do not introduce decision software,
- do not enable live submit.
