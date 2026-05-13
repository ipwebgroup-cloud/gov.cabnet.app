# V3 Live-Submit Gate Config Hygiene

## Purpose

This patch cleans the V3 live-submit master gate after the disabled server-only config was installed.

It fixes two small issues:

1. The gate could display a blank `Config load error:` block when a config file loaded successfully.
2. The gate now accepts both canonical and installer-generated acknowledgement keys:
   - `acknowledgement` and `acknowledgement_phrase`
   - `required_acknowledgement` and `required_acknowledgement_phrase`

It also exposes the `hard_enable_live_submit` flag in the gate result and dashboard.

## Safety

- No EDXEIX calls.
- No AADE calls.
- No database writes.
- No production submission_jobs writes.
- No production submission_attempts writes.
- Does not touch `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`.
- Disabled config remains closed.

## Expected state after deploy

With the disabled config installed, the gate should show:

- Config loaded: yes
- Config error: -
- Enabled: no
- Mode: disabled
- Adapter: disabled
- Hard enable live submit: no
- OK for future live submit: no

