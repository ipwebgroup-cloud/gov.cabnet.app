# v5.2 Driver Receipt Email Template Polish

## What changed

Polishes the HTML receipt copy sent to drivers after a Bolt pre-ride email is imported.

The receipt now has:

- LUX LIMO logo header.
- Presentation-ready white card design.
- Driver / vehicle / total summary cards.
- Route summary section.
- Full ride details table.
- VAT/TAX included section at 13%.
- Company stamp section.
- Safety note showing no EDXEIX submission was performed by this notification.

## Files included

```text
public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
docs/BOLT_DRIVER_RECEIPT_TEMPLATE_V5_2.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload paths

```text
public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg
→ /home/cabnet/public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg

gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
→ /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
ls -l /home/cabnet/public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg
```

## Safety

This patch only changes the receipt email template and adds a public logo asset. It does not call Bolt, call EDXEIX, create jobs, create attempts, or alter live-submit behavior.
