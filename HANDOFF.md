# gov.cabnet.app — Handoff after v5.5 AADE/myDATA Test Adapter Readiness

Current state:

- Driver pre-ride email copy works.
- Driver receipt copies must remain disabled until AADE/myDATA official issuance succeeds.
- Server config has `receipts.mode=aade_mydata` and AADE credentials present server-side only.
- v5.5 adds AADE/myDATA readiness and connectivity tooling, but does not transmit invoices.
- Live EDXEIX remains guarded/session-disconnected unless Andreas changes it explicitly.

Important files:

- `/home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php`
- `/home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/aade-mydata-readiness.php`
- `/home/cabnet/gov.cabnet.app_sql/2026_05_07_receipt_issuance_attempts.sql`

Next safe step:

1. Install v5.5 SQL.
2. Run syntax checks.
3. Open AADE readiness page.
4. Run CLI readiness.
5. Run optional connectivity ping against test environment.
6. Do not enable receipt copy until official AADE issuance is implemented and confirmed.
