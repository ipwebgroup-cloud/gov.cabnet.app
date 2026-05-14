# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current checkpoint: `v3.0.50-v3-proof-dashboard`

## Project identity

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Live server is not a cloned Git repo
- V0 laptop/manual production helper must remain untouched
- V3 is the server/PC automation development path

## Current verified V3 state

V3 readiness path has been proven with a forwarded Gmail/Bolt-style pre-ride email.

Verified path:

```text
Gmail/manual forward
→ server mailbox
→ V3 intake
→ parser
→ driver / vehicle / lessor mapping
→ future-safe guard
→ verified starting-point guard
→ submit_dry_run_ready
→ live_submit_ready
```

Payload audit result:

```text
Rows checked: 1
Payload-ready: 1
Blocked: 0
Warnings: 0
No EDXEIX call
No AADE call
No queue status change
```

Final rehearsal result:

```text
Rows checked: 1
Pre-live passed: 0
Blocked: 1
No EDXEIX call
No AADE call
No DB writes
```

Gate blocks were correct:

```text
enabled is false
mode is not live
required acknowledgement phrase is not present
adapter is disabled
hard_enable_live_submit is false
approval: no valid operator approval found
```

## Proof row

The main proof row was:

```text
queue_id: 56
queue_status: live_submit_ready
customer: Arnaud BAGORO
driver: Filippos Giannakopoulos
vehicle: EHA2545
lessor_id: 3814
driver_id: 17585
vehicle_id: 5949
starting_point_id: 6467495
```

This was a forwarded/synthetic test email. It is valid proof of V3 pipeline readiness, not sufficient proof to open live submit.

## Critical mapping facts

For lessor `3814`:

```text
6467495 = ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα
```

For lessor `2307`:

```text
1455969 = ΧΩΡΑ ΜΥΚΟΝΟΥ
9700559 = ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ
```

Old `6467495` was invalid for lessor `2307`, but valid for lessor `3814`.

## Recent fixes

- Repaired V3 pulse cron lock ownership.
- Added V3 storage check and lock owner hardening.
- Added compact monitor, queue focus, pulse focus, readiness focus, and Ops index V3 entry.
- Added lessor `3814` starting-point option to `pre_ride_email_v3_starting_point_options`.
- Fixed live-readiness worker starting-point option alias query:
  - old: `lessor_id`, `starting_point_id`
  - correct: `edxeix_lessor_id`, `edxeix_starting_point_id`
- Added V3 proof dashboard.

## Current safety posture

```text
V3 automation readiness path: PROVEN
V3 live auto-submit: DISABLED
Master gate: CLOSED
Adapter: disabled
Hard enable: false
Operator approval: required
V0 laptop/manual helper: untouched
AADE: untouched
Production submission tables: untouched
```

## Important pages

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

## Next recommended phase

`Phase V3.1 — Closed-Gate Live Adapter Preparation`

Do not enable live submit yet.

Recommended next steps:

1. Finalize V3-to-EDXEIX live adapter field map.
2. Add dry-run live package export to local artifacts only.
3. Improve operator approval visibility.
4. Build closed-gate live adapter skeleton that cannot submit while gate is closed.
5. Run another future forwarded email test.
6. Only later prepare a separate live-submit opening plan.
