# gov.cabnet.app — V3 Bolt Pre-Ride Automation Status

Version checkpoint: v3.0.49 planning checkpoint  
Project: gov.cabnet.app Bolt → EDXEIX bridge  
Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow  
Status date: 2026-05-14

## Current operational boundary

V0 remains the manual laptop production helper and must not be touched by V3 work.

V3 is the PC/server-side automation path. V3 currently performs automated intake, parsing, mapping, guard checks, dry-run readiness, live-readiness status promotion, payload audit, and final rehearsal behind a closed master gate.

Live EDXEIX submission remains disabled.

## Verified V3 proof

A forwarded Gmail/Bolt-style pre-ride email was sent to the server mailbox and successfully traveled through the V3 pipeline:

```text
Gmail/manual forward
→ server mailbox
→ V3 maildir intake
→ parser
→ mapping
→ future-safe guard
→ starting-point verified
→ submit_dry_run_ready
→ live_submit_ready
→ payload audit PAYLOAD-READY
→ final rehearsal BLOCKED by closed master gate
```

Proof row:

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
last_error: NULL
```

The rehearsal correctly blocked the row because the live-submit master gate remains closed:

```text
enabled=false
mode=disabled
adapter=disabled
required acknowledgement phrase absent
hard_enable_live_submit=false
operator approval missing
```

## Confirmed safe behavior

V3 did not call EDXEIX.  
V3 did not call AADE.  
V3 did not write to production submission tables.  
V3 did not change V0 files or dependencies.  
V3 did not enable live submit.

## Important fixes completed

- Pulse lock directory and lock file are now healthy.
- Pulse lock file is owned by `cabnet:cabnet` with `0660` permissions.
- V3 storage check detects lock ownership/writability problems.
- V3 lessor 3814 starting-point option was added to the V3 verified options table:

```text
3814 / 6467495 = ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα
```

- V3 live-readiness worker was patched to use the real start-options columns:

```text
edxeix_lessor_id
edxeix_starting_point_id
```

instead of old aliases:

```text
lessor_id
starting_point_id
```

## Verified V3 monitoring pages

```text
/ops/pre-ride-email-v3-dashboard.php
/ops/pre-ride-email-v3-monitor.php
/ops/pre-ride-email-v3-queue-focus.php
/ops/pre-ride-email-v3-pulse-focus.php
/ops/pre-ride-email-v3-readiness-focus.php
/ops/pre-ride-email-v3-storage-check.php
/ops/pre-ride-email-v3-live-submit-gate.php
/ops/pre-ride-email-v3-live-payload-audit.php
```

## Current next phase

Phase V3.1 should prepare the closed-gate live adapter path while keeping live submit disabled.

Recommended order:

1. Add V3 proof dashboard.
2. Create the EDXEIX live adapter field map.
3. Add dry-run live-package export artifacts.
4. Improve operator approval visibility.
5. Add a closed-gate adapter skeleton.
6. Test again with a future forwarded email.
7. Only later, after explicit approval, prepare a live-submit opening plan.

## Hard rule

No live EDXEIX submission is enabled unless Andreas explicitly requests that specific live-submit gate opening work.
