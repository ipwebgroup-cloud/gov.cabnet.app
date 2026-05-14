# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated through: v3.0.45-v3-ops-home-integration

## Project identity

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Server layout:
  - `/home/cabnet/public_html/gov.cabnet.app`
  - `/home/cabnet/gov.cabnet.app_app`
  - `/home/cabnet/gov.cabnet.app_config`
  - `/home/cabnet/gov.cabnet.app_sql`
  - `/home/cabnet/tools/firefox-edxeix-autofill-helper`

## Current operating boundary

- V0 is installed on the laptop and remains the manual/production helper path.
- V3 is installed on the PC/server and remains the development/automation path.
- Do not touch V0 production helper files or dependencies unless Andreas explicitly asks.
- Live EDXEIX submit remains disabled.

## Current verified V3 posture

- V3 storage check as `cabnet`: OK.
- Pulse lock file: OK.
- Pulse lock owner/group: `cabnet:cabnet`.
- Pulse lock perms: `0660`.
- Pulse cron is healthy:
  - `cycles_run=5`
  - `ok=5`
  - `failed=0`
  - `finish exit_code=0`
- V3 queue currently has only previous blocked rows; wait for a new future-safe Bolt pre-ride email for the next real proof.

## Important V3 pages

- V3 Control Center: `/ops/pre-ride-email-v3-dashboard.php`
- V3 Compact Monitor: `/ops/pre-ride-email-v3-monitor.php`
- V3 Queue Focus: `/ops/pre-ride-email-v3-queue-focus.php`
- V3 Pulse Focus: `/ops/pre-ride-email-v3-pulse-focus.php`
- V3 Readiness Focus: `/ops/pre-ride-email-v3-readiness-focus.php`
- V3 Storage Check: `/ops/pre-ride-email-v3-storage-check.php`
- Queue Watch: `/ops/pre-ride-email-v3-queue-watch.php`
- Pulse Runner: `/ops/pre-ride-email-v3-fast-pipeline-pulse.php`

## Recent verified patches

- v3.0.39 — V3 storage check and V0/V3 boundary docs.
- v3.0.40 — Pulse lock owner hardening.
- v3.0.41 — V3 compact monitor.
- v3.0.42 — V3 queue focus.
- v3.0.43 — V3 pulse focus.
- v3.0.44 — V3 readiness focus.
- v3.0.45 — V3 Control Center integration with verified focus pages.

## Operator rule

Do not manually test the V3 pulse cron worker as root. Use:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"
```

For live operations, Andreas uses judgment. V3 must provide visibility only unless explicitly approved otherwise.
