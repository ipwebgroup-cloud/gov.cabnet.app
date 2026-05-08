# gov.cabnet.app Handoff — v6.2.8

## Current state

- Bolt mail intake is live.
- AADE/myDATA production receipt issuing is live.
- Driver receipt PDF email copy is live.
- EDXEIX live submission remains blocked.
- `submission_jobs = 0` and `submission_attempts = 0` must remain true unless Andreas explicitly approves EDXEIX live submission.

## Critical event

During a live ride for intake 26:

- customer: Diego Rodrigue
- driver: Efthymios Giakis
- plate: ITK7702
- parsed pickup: 2026-05-08 15:31:18 EEST
- estimated price: 50.00 - 55.00 eur

The emergency intake-based flow created booking 67 and sent the receipt after pickup. This proved the reliable fallback: use Bolt pre-ride email intake for receipt preparation and issue at pickup, instead of waiting for Bolt API finish data.

## v6.2.8 change

Added:

```text
/home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
```

This worker scans recent parsed Bolt mail intake rows, creates/links a receipt-only local booking if needed, waits for the existing pickup-time gate, then calls the existing duplicate-protected AADE issuer and driver email system.

## Recommended cron

```cron
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --minutes=240 --limit=25 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_receipts.log 2>&1
```

## Safety rules

- Do not enable EDXEIX live submission.
- Do not create submission jobs or attempts.
- Do not reissue already-issued receipts.
- Do not expose credentials.
- Keep emergency scripts removed after v6.2.8 worker is deployed and verified.

## Next step

Deploy v6.2.8, run dry-run, run one live tick, add the cron, then monitor the next live transfer.
