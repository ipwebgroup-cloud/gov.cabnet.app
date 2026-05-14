# V3 Adapter Contract Probe

Version: `v3.0.56-v3-adapter-contract-probe`

This patch adds a V3-only read-only adapter contract probe for the closed-gate live adapter preparation phase.

## Purpose

The probe verifies that the V3 adapter classes can be loaded, instantiated, and called with a local fixture payload while preserving the safety boundary.

It checks:

- `LiveSubmitAdapterV3` interface file
- `DisabledLiveSubmitAdapterV3`
- `DryRunLiveSubmitAdapterV3`
- `EdxeixLiveSubmitAdapterV3` skeleton
- `submit()` result envelopes
- whether any adapter returns `submitted=true`

## Safety boundary

The probe does not:

- call Bolt
- call EDXEIX
- call AADE
- change queue rows
- write production submission tables
- change SQL schema
- touch V0
- enable live submit

## CLI

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php --json"
```

## Ops page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-contract-probe.php
```

## Expected result

The probe should report that all adapters are safe for the closed-gate phase and that no adapter returned `submitted=true`.

The future real adapter skeleton should exist but remain blocked and not live-capable until Andreas explicitly approves live-submit work.
