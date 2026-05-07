# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge v5.2.2

Current state: guarded live-submit path exists but EDXEIX session remains explicitly disconnected. Driver copy and receipt emails are enabled. v5.2.2 fixes receipt email transport by sending HTML receipts as base64-wrapped MIME content to avoid long-line SMTP rejection.

Safety posture remains:

- app.dry_run=true
- live submit armed but blocked by edxeix_session_connected=false
- no live submit cron
- submission_jobs=0 expected
- submission_attempts=0 expected
- EDXEIX POST blocked unless explicit future gates are opened

Changed file:

- gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
