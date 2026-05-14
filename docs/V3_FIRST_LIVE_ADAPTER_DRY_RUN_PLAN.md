# V3 First Live Adapter Dry-Run Plan

Version: v3.0.66-v3-real-adapter-design-spec

## Objective

Define the next safe dry-run steps before any future real EDXEIX adapter behavior.

## Step 1 — Keep live submit disabled

Do not change:

```text
/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
```

Expected state:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
```

## Step 2 — Build adapter validation only

Next code patch may add validation helpers inside the future adapter skeleton, but must keep:

```php
isLiveCapable() === false
submitted === false
```

## Step 3 — Test with local fixture

Use the existing contract probe:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php"
```

Expected:

```text
future_real safe_for_closed_gate=yes
submitted=no
live_capable=no
```

## Step 4 — Test with a real V3 package artifact

Use latest local package artifacts from:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages
```

The adapter dry-run must only validate and hash payload. It must not call EDXEIX.

## Step 5 — Run switchboard

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --json"
```

Expected:

```text
ok=false
eligible_for_live_submit_now=false
```

## Stop condition

If any patch causes any of the following, stop and rollback:

- EDXEIX call appears in logs
- `submitted=true` without explicit approved live test
- config changed to live accidentally
- production submission tables are written unexpectedly
- V0 files are modified
- AADE behavior changes
