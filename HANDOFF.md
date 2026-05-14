# HANDOFF — gov.cabnet.app V3 Automation

## Current phase

V3 Bolt pre-ride email automation is in closed-gate pre-live preparation. Live submit remains disabled.

## Verified state

- V3 intake/pulse path is proven.
- Fresh future-safe rows have reached `live_submit_ready`.
- Operator approval workflow is proven.
- Payload audit is proven.
- Package export is proven.
- Final rehearsal accepts valid approval and blocks on master gate.
- Kill-switch check accepts valid approval and blocks on master gate/adapter.
- Pre-live switchboard loads in browser using direct DB/config read-only rendering.
- Adapter row simulation is proven with the future adapter skeleton.
- Adapter skeleton remains non-live-capable and returns `submitted=false`.
- Live submit remains disabled.
- V0 is untouched.

## Current patch

`v3.0.69-v3-adapter-payload-consistency-harness`

Adds a V3-only read-only consistency harness that compares:

- DB-built EDXEIX payload fields,
- latest local package export `edxeix_fields.json`,
- future adapter skeleton returned payload hash.

## Safety rules

- Do not enable live submit unless Andreas explicitly asks for a live-submit update.
- Do not touch V0 production or dependencies.
- Do not call Bolt, EDXEIX, or AADE from V3 test tools.
- Do not write queue status from proof/switchboard/simulation/consistency tools.
- Do not commit server-only real config or credentials.

## Main V3 URLs

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php
https://gov.cabnet.app/ops/pre-ride-email-v3-live-package-export.php
https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php
https://gov.cabnet.app/ops/pre-ride-email-v3-proof.php
```

## Next safest step

Run and verify v3.0.69. Then create a commit checkpoint.

Potential next phase:

`v3.0.70-v3-historical-proof-index` — read-only proof/artifact/event index.
