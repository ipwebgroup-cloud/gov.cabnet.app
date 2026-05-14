# gov.cabnet.app — V3 Automation Pre-Live Status

Updated: 2026-05-14 11:55 UTC  
Patch: `v3.0.74-v3-live-gate-drift-guard`

## Current posture

V3 forwarded-email automation is in a proven pre-live state, but live EDXEIX submission remains intentionally closed.

Confirmed from the latest server outputs in this working session:

- V3 fast pipeline can intake forwarded Bolt pre-ride emails.
- Valid future rows can reach `live_submit_ready`.
- Expired/past rows are safely blocked by the expiry guard.
- Starting-point IDs are validated against operator-verified options.
- Operator approval workflow works for closed-gate rehearsal only.
- Local EDXEIX field package export works and writes only private artifacts.
- Adapter contract probe confirms disabled, dry-run, and skeleton future adapter contracts.
- Adapter row simulation confirms the future adapter skeleton does not submit.
- Payload consistency harness confirms DB payload, exported artifact payload, and adapter payload hashes match.
- Proof bundle export now produces local proof summaries under private storage.
- Proof ledger indexes proof bundles and package exports.
- New live gate drift guard verifies the gate remains in the expected disabled pre-live posture.

## Safety invariants

The V3 pre-live system must continue to preserve these invariants:

```text
No Bolt call
No EDXEIX call
No AADE call
No DB writes from read-only diagnostics
No queue status changes from read-only diagnostics
No production submission tables touched
V0 untouched
```

## New in v3.0.74

Added a read-only V3 live gate drift guard:

- CLI: `gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php`
- Ops: `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php`

The guard checks:

- `pre_ride_email_v3_live_submit.php` exists, is readable, and returns an array.
- Current gate fields: `enabled`, `mode`, `adapter`, `hard_enable_live_submit`, acknowledgement phrase presence.
- Whether the gate still matches the expected disabled pre-live posture.
- Whether partial live signals appear accidentally.
- Whether the full live switch appears open.
- Whether the future EDXEIX adapter file looks network-aware or live-capable by static scan.
- Latest proof bundle and local package export artifact presence.

The Ops page does not execute commands. It only reads files.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php --json"
```

Ops URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php
```

Expected normal result while live submit is disabled:

```text
OK: yes
Expected disabled pre-live posture: yes
Live risk detected: no
Full live switch looks open: no
```

## Do not do yet

Do not enable live EDXEIX submission yet.

Do not change the live gate config to:

```text
enabled=true
mode=live
adapter=edxeix_live
hard_enable_live_submit=true
```

until Andreas explicitly approves a live-submit update and there is a real eligible future Bolt row with enough time remaining.

## Next safe phase

Next safe development phase should be one of:

1. Add a read-only final cutover checklist that requires a real current `live_submit_ready` row and valid approval before showing any live-readiness as green.
2. Add a non-network adapter execution recorder that can later write a local immutable attempt envelope before any real adapter is implemented.
3. Improve Ops navigation to expose the proof ledger, gate drift guard, and proof bundle exporter together.

Live adapter implementation remains a separate explicit phase and must not be activated accidentally.
