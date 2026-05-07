# HANDOFF — gov.cabnet.app Bolt → EDXEIX / AADE Bridge v5.8.1

Current state:

- Bolt mail intake and driver pre-ride copy are active.
- AADE/myDATA production connectivity is validated.
- v5.8 automatic AADE receipt issuance is armed for new real Bolt mail bookings.
- v5.8.1 adds a timing gate: AADE receipt issuance and official receipt email wait until the booking pick-up time.
- For `source='bolt_mail'`, `normalized_bookings.started_at` is treated as the parsed Bolt pick-up time.
- Existing gates remain: `auto_issue_not_before`, duplicate protection, test/synthetic blocking, AADE-only receipt mode, no generated fallback, no EDXEIX calls, no submission jobs/attempts.

Verification:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --limit=5 --json
```
