You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Continue from the latest committed checkpoint:

- v3.0.80–v3.0.99 legacy public utility audit/readiness milestone is committed.
- Production `/ops/pre-ride-email-tool.php` is untouched.
- No legacy public-root utilities were moved/deleted/redirected.
- Live EDXEIX submission remains disabled and the V3 gate remains closed.

Current next patch:

- v3.1.0 V3 real-mail intake + queue health audit.
- Adds CLI `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php`.
- Adds ops page `https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php`.
- Read-only only: no Bolt call, no EDXEIX call, no AADE call, no DB writes, no queue mutations, no filesystem writes.

After upload, verify syntax, auth redirect, JSON output, and live gate risk status. Do not enable live submission.
