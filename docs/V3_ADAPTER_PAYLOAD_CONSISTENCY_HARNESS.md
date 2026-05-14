# V3 Adapter Payload Consistency Harness

Version: `v3.0.69-v3-adapter-payload-consistency-harness`

## Purpose

Adds a V3-only read-only consistency harness that compares the final EDXEIX field package across the local V3 automation surfaces:

1. the DB-built EDXEIX package from `pre_ride_email_v3_queue`,
2. the latest local package export artifact `queue_<id>_*_edxeix_fields.json`,
3. the local future adapter skeleton payload hash returned by `EdxeixLiveSubmitAdapterV3`.

The harness is intended to prove that the same final field package is being handed through the package-export and adapter-simulation paths before any future real adapter implementation.

## Safety boundary

This patch does not:

- call Bolt,
- call EDXEIX,
- call AADE,
- write DB rows,
- change queue status,
- write production submission tables,
- touch V0,
- enable live submit,
- change SQL,
- change cron schedules.

If the future adapter ever reports `isLiveCapable() = true`, the harness intentionally does **not** call `submit()`.

## Files

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php
```

## CLI usage

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --queue-id=427 --json"
```

## Ops page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php
```

Optional row-specific URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php?queue_id=427
```

## Expected safe result

The harness should show:

```text
Simulation safe: yes
adapter live_capable=no
adapter submitted=no
No EDXEIX call
No AADE call
V0 untouched
```

If a row has no package export artifact, the harness should show a consistency block explaining the missing artifact. Run package export for that row first if a strict package-vs-adapter comparison is required.

## Notes

The harness is a payload consistency proof. It does not decide whether live submit is allowed. The master gate, operator approval, expiry guard, kill-switch check, and final rehearsal remain the live-submit control surfaces.
