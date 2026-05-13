# V3 Disabled Live-Submit Config Installer

Adds a safe CLI installer for a server-only V3 live-submit config file in a closed/disabled state.

## Purpose

The V3 master gate currently closes because the server-only config is missing. This patch lets the server load a real config while still blocking live submit.

## Safety

The generated config uses:

- `enabled => false`
- `mode => disabled`
- `adapter => disabled`
- `hard_enable_live_submit => false`
- empty acknowledgement phrase

The installer does not call EDXEIX, does not call AADE, does not write to the database, and does not touch `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`.

## Install

Upload:

`gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php`

To:

`/home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php`

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php
php /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php --dry-run
php /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php --write
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
```

Expected gate result after install:

- config loaded: yes
- enabled: no
- mode: disabled
- adapter: disabled
- OK for future live submit: no

## Notes

The generated file is server-only and should remain outside Git:

`/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php`
