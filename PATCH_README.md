# Patch: V3 Disabled Live-Submit Config Installer

## What changed

Adds a CLI installer that creates `/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php` in a disabled state so the V3 master gate can load a config without enabling live submit.

## Files included

- `gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php`
- `docs/PRE_RIDE_EMAIL_TOOL_V3_DISABLED_LIVE_CONFIG.md`
- `PATCH_README.md`

## Upload path

`gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php`
→ `/home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php`

## Commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php
php /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php --dry-run
php /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_email_v3_disabled_live_submit_config.php --write
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
```

## Expected result

The V3 gate should show config loaded, but remain closed:

- Enabled: no
- Mode: disabled
- Adapter: disabled
- Hard live submit: no

## Safety

- No EDXEIX call
- No AADE call
- No DB writes
- No production submission table writes
- No production `pre-ride-email-tool.php` change
- Generated config remains disabled
