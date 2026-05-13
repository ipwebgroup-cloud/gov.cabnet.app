# V3 Live-Submit Payload Audit

Adds a final payload builder/audit layer for the isolated V3 pre-ride email automation path.

## Purpose

This patch builds the exact EDXEIX form-field package that a future V3 live-submit adapter would use from `live_submit_ready` rows.

It does not call EDXEIX. It does not submit anything.

## Added files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-payload-audit.php`

## Safety

- No EDXEIX calls
- No AADE calls
- No production submission tables
- No queue status changes
- Optional audit mode writes only throttled V3 queue events

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-payload-audit.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php --limit=20
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-payload-audit.php
```
