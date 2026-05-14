# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current state after v3.0.41:

- V0 laptop/manual production helper remains untouched.
- V3 PC/server automation path continues in safe read-only/dry-run mode.
- Live EDXEIX submit remains disabled.
- V3 pulse cron lock ownership issue was fixed on server: `pre_ride_email_v3_fast_pipeline_pulse.lock` is now `cabnet:cabnet` with `0660` permissions.
- V3 storage check v3.0.40 verifies lock/log/pulse prerequisites and warns not to run the V3 pulse cron worker as root.
- v3.0.41 adds a compact read-only V3 monitor page:
  `/ops/pre-ride-email-v3-monitor.php`

Operational rule:

- Andreas may use V0 manually when needed. No software decision layer should decide that.
- V3 pages should provide visibility only unless explicitly asked otherwise.
- Do not touch V0 production or dependencies.
- Do not enable live submit.

Key monitoring URLs:

- `/ops/pre-ride-email-v3-monitor.php`
- `/ops/pre-ride-email-v3-dashboard.php`
- `/ops/pre-ride-email-v3-queue-watch.php`
- `/ops/pre-ride-email-v3-fast-pipeline-pulse.php`
- `/ops/pre-ride-email-v3-storage-check.php`

Next safe work:

- Continue V3-only UI polish and visibility.
- Polish existing Queue Watch / Pulse Monitor / Automation Readiness pages only after inspecting their current server code or replacing them with additive read-only pages.
