You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Continue from checkpoint `v3.0.50-v3-proof-dashboard`.

Critical rules:
- Plain PHP/mysqli/cPanel only.
- Do not touch V0 laptop/manual production helper or V0 dependencies.
- Do not enable live EDXEIX submit unless Andreas explicitly asks for a live-submit update.
- Live submit remains disabled behind the master gate.
- No AADE behavior changes unless explicitly requested.
- Prefer read-only, dry-run, audit, rehearsal, package export, and closed-gate behavior.
- Never expose credentials or real config secrets.

Current verified V3 state:
- Forwarded Gmail/Bolt-style pre-ride email test succeeded.
- V3 intake parsed the email.
- V3 mapped driver/vehicle/lessor.
- V3 verified starting point.
- V3 reached `submit_dry_run_ready`.
- V3 reached `live_submit_ready`.
- Payload audit returned payload-ready.
- Final rehearsal correctly blocked due to closed master gate.
- V0 remained untouched.
- No EDXEIX or AADE calls occurred.

Proof row:
```text
queue_id: 56
status: live_submit_ready
customer: Arnaud BAGORO
driver: Filippos Giannakopoulos
vehicle: EHA2545
lessor_id: 3814
driver_id: 17585
vehicle_id: 5949
starting_point_id: 6467495
```

Important current pages:
```text
https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
https://gov.cabnet.app/ops/pre-ride-email-v3-proof.php
https://gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
https://gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
```

Next recommended phase:
`Phase V3.1 — Closed-Gate Live Adapter Preparation`

Recommended next patch:
- Finalize `docs/V3_EDXEIX_LIVE_ADAPTER_FIELD_MAP.md`.
- Then add a dry-run live package export that writes local artifacts only.
- Then improve operator approval visibility.
- Then build a closed-gate adapter skeleton that cannot submit while the gate remains closed.

Do not build or enable real live submit until Andreas explicitly approves.
