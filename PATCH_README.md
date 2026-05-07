# gov.cabnet.app v5.8 Automatic AADE Receipt Issuance Patch

## Files included

- `gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php`
- `gov.cabnet.app_app/src/Mail/BoltMailAutoDryRunService.php`
- `gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php`
- `gov.cabnet.app_config_examples/aade_auto_receipt.example.php`
- `docs/BOLT_AADE_AUTO_RECEIPT_ISSUANCE_V5_8.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## SQL

No new SQL required. Uses existing `receipt_issuance_attempts` and `bolt_mail_driver_notifications` columns.

## Upload paths

Upload files directly to the matching live paths under `/home/cabnet`.

## Safety

Automatic AADE/myDATA issuance is enabled only by explicit private config flags. It does not call EDXEIX and does not create `submission_jobs` or `submission_attempts`.
