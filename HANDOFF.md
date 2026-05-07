# gov.cabnet.app — Handoff after v5.2 Driver Receipt Template Polish

Current state:
- v5.0 guarded live-submit path is armed but blocked by `edxeix_session_connected=false` and one-shot lock requirements.
- v5.1 added the second driver receipt email with VAT/TAX included and company stamp.
- v5.2 polished the HTML receipt email template and added the LUX LIMO logo asset.

Safety posture:
- No automatic live-submit cron exists.
- Live EDXEIX POST remains blocked until explicit session connection, one-shot booking lock, valid future booking, mapping, and confirmation requirements pass.
- v5.2 does not affect EDXEIX submission logic.

Changed/added files in v5.2:
- `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `/home/cabnet/public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg`
- `docs/BOLT_DRIVER_RECEIPT_TEMPLATE_V5_2.md`

Next recommended technical work:
- v5.3 EDXEIX partner/driver mapping matrix before any real live-submit attempt.
