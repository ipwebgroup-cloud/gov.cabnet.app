# gov.cabnet.app — Bolt Mail Driver Notifications v4.5/v4.5.1

The original v4.5 driver notification layer added one idempotent driver email copy per newly imported real Bolt pre-ride email.

v4.5.1 changes the recipient lookup model:

- Preferred: `mapping_drivers.driver_email`, populated from the Bolt Driver Directory API sync.
- Emergency fallback only: empty manual config arrays.
- Synthetic/test emails remain suppressed.
- Notification audit rows are stored in `bolt_mail_driver_notifications`.
- No EDXEIX jobs, attempts, or live POSTs are created.

See `docs/BOLT_DRIVER_DIRECTORY_EMAIL_SYNC_V4_5_1.md` for the current production setup.
