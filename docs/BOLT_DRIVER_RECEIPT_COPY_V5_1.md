# gov.cabnet.app — v5.1 Driver Receipt Copy

## Purpose

Adds a second driver email after each successful Bolt pre-ride driver copy. The second email is an HTML receipt copy containing the same ride details plus a VAT/TAX section and the LUX LIMO company stamp.

## Scope

Changed/added files only:

- `gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`
- `public_html/gov.cabnet.app/ops/mail-driver-notifications.php`
- `public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg`
- `gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_receipt_columns.sql`

## Behavior

For a newly imported real Bolt pre-ride email:

1. The existing plain-text driver copy is sent.
2. If the first copy succeeds, a second HTML receipt copy is sent to the same driver identity recipient.
3. The receipt includes the same ride details shown in the original driver copy.
4. The receipt VAT/TAX section assumes VAT is included in the estimated total at 13%.
5. Estimated price ranges are normalized to the first value only, e.g. `40.00 - 44.00 eur` becomes `40.00 EUR`.
6. The company stamp is included from `/assets/stamps/lux-limo-stamp.jpg`.

## VAT calculation

If the estimated total is `40.00 EUR` and VAT is 13% included:

- Gross total: `40.00 EUR`
- Net before VAT: `40 / 1.13 = 35.40 EUR`
- VAT included: `40.00 - 35.40 = 4.60 EUR`

## Safety

This patch does not:

- enable live EDXEIX submission
- call EDXEIX
- create submission jobs
- create submission attempts
- change normalized bookings
- change dry-run evidence
- change EDXEIX payload generation

It only adds a second email notification and audit columns for the receipt status.

## Optional config

The defaults work without changing config. Optional keys under `mail.driver_notifications`:

```php
'receipt_copy_enabled' => true,
'receipt_subject_prefix' => 'Bolt pre-ride receipt',
'receipt_vat_rate_percent' => 13,
'receipt_stamp_url' => 'https://gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg',
```

Use `receipt_copy_enabled => false` only if the receipt copy must be temporarily disabled while keeping the normal driver copy active.
