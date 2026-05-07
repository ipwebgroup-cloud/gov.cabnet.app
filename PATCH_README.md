# gov.cabnet.app v5.8.1 — AADE Receipt Pick-up Time Gate

## What changed

The automatic AADE receipt flow now waits until the booking pick-up time before issuing the official AADE/myDATA receipt and sending the driver receipt email.

For Bolt mail bookings, `normalized_bookings.started_at` is the parsed Bolt pick-up time. The earlier Bolt `Start time` from the email is not used to trigger the receipt email.

## Files included

```text
gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
docs/BOLT_AADE_RECEIPT_PICKUP_TIME_GATE_V5_8_1.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload path

```text
gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
→ /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --limit=5 --json
```

## Expected behavior

Before pick-up time, the worker should show:

```text
pickup_time_not_reached
```

At or after pick-up time, the existing v5.8 AADE SendInvoices + official driver receipt email flow can proceed if all other gates pass.

## Safety

This patch does not call AADE on install, does not call EDXEIX, does not create submission jobs/attempts, and does not change config or database schema.
