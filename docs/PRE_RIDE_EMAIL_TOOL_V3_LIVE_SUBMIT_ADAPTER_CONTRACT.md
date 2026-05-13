# V3 Live-Submit Adapter Contract Probe

Adds a disabled/dry-run adapter contract layer for the isolated V3 pre-ride email automation path.

## Purpose

This is the next safe step toward full automation. It creates the adapter interface and two non-live implementations:

- `DisabledLiveSubmitAdapterV3` — always blocks and never calls EDXEIX.
- `DryRunLiveSubmitAdapterV3` — validates the final field package and never calls EDXEIX.

The probe CLI and page can inspect `live_submit_ready` rows and run the disabled/dry-run adapter path without submitting anything.

## Safety

- No EDXEIX calls.
- No AADE calls.
- No DB writes.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Production `pre-ride-email-tool.php` is untouched.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/LiveSubmitAdapterV3.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-adapter.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php --limit=20
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php --limit=20 --adapter=dry-run
```

## URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-submit-adapter.php
```
