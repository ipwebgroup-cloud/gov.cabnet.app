# HANDOFF — gov.cabnet.app V3 Ops Monitoring

Current verified state after `v3.0.46-ops-index-v3-entry`:

- V0 laptop/manual production helper remains untouched.
- V3 server-side monitoring path is active.
- Live EDXEIX submission remains disabled.
- Pulse cron is healthy as the `cabnet` user.
- Pulse lock file is verified as `cabnet:cabnet` with `0660` permissions.
- V3 storage check is OK.
- `/ops/index.php` now clearly links the verified V3 monitoring pages.

Verified V3 pages:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
https://gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
```

Main Ops entry:

```text
https://gov.cabnet.app/ops/index.php
```

Safety boundary:

- Do not touch V0 production helper unless Andreas explicitly asks.
- Do not enable live EDXEIX submission.
- Keep V3 changes read-only/visibility-first unless explicitly approved.
- Manual pulse cron-worker testing should be done as `cabnet`, not root.
