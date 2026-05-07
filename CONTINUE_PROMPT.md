Continue gov.cabnet.app Bolt → EDXEIX bridge from v5.5.

Current focus: AADE/myDATA official receipt integration.

Safety rules:
- Do not send generated/pro-forma receipts as official receipts.
- Keep `mail.driver_notifications.receipt_copy_enabled=false` until AADE/myDATA issuance succeeds.
- Do not paste or expose AADE credentials.
- Use test environment first.
- Do not call `SendInvoices` except in an explicit sandbox phase with accountant-approved payload fields.
- Keep EDXEIX live submission guarded and no automatic live cron.

v5.5 added:
- `src/Receipts/AadeMyDataClient.php`
- `cli/aade_mydata_readiness.php`
- `ops/aade-mydata-readiness.php`
- `receipt_issuance_attempts` audit migration
- hard block in driver receipt service for `receipt_pdf_mode=aade_mydata` so it skips instead of falling back to generated/static PDFs.

Next likely phase:
- v5.6 AADE/myDATA sandbox XML payload builder, using official docs and accountant-confirmed document type/classifications/payment method.
