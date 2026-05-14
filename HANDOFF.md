# HANDOFF — gov.cabnet.app V3 Automation

You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

## Project identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Do not introduce frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.
- Server layout:
  - `/home/cabnet/public_html/gov.cabnet.app`
  - `/home/cabnet/gov.cabnet.app_app`
  - `/home/cabnet/gov.cabnet.app_config`
  - `/home/cabnet/gov.cabnet.app_sql`
  - `/home/cabnet/tools/firefox-edxeix-autofill-helper`

## Operational boundary

- V0 is the laptop/manual production helper. Do not touch V0 or its dependencies unless Andreas explicitly asks.
- V3 is the server/PC automation path.
- Live submit remains disabled.
- No EDXEIX live call is allowed unless Andreas explicitly asks for a live-submit update.
- No AADE changes are part of this phase.

## Current verified V3 state

As of `v3.0.59-v3-approval-rehearsal-proof-checkpoint`:

```text
V3 readiness pipeline: proven
Forwarded/future email intake: proven
Parser/mapping/future guard: proven
Starting-point verification: proven
submit_dry_run_ready: proven
live_submit_ready: proven
Payload audit: proven
Final rehearsal gate block: proven
Local package export: proven
Operator approval workflow: proven
Closed-gate diagnostics: proven
Future adapter skeleton: installed but not live-capable
Adapter contract probe: proven
Live EDXEIX submit: disabled
V0: untouched
```

## Important proof rows

### Row 418

```text
queue_status: live_submit_ready during test
customer: Marina Ganejeva
driver: Efthymios Giakis
vehicle: ITK7702
lessor_id: 2307
driver_id: 17852
vehicle_id: 11187
starting_point_id: 1455969
approval: inserted for closed_gate_rehearsal_only
payload audit: OK
package export: OK
final rehearsal: blocked only by master gate
```

### Row 56

Historical forwarded-email proof row. Reached `live_submit_ready`, then expired/blocked safely after pickup time passed.

## Critical data facts

For lessor `2307`:

```text
1455969 = ΧΩΡΑ ΜΥΚΟΝΟΥ
9700559 = ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ
```

For lessor `3814`:

```text
6467495 = ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα
```

## Current important pages

```text
/ops/pre-ride-email-v3-dashboard.php
/ops/pre-ride-email-v3-monitor.php
/ops/pre-ride-email-v3-proof.php
/ops/pre-ride-email-v3-queue-focus.php
/ops/pre-ride-email-v3-pulse-focus.php
/ops/pre-ride-email-v3-readiness-focus.php
/ops/pre-ride-email-v3-storage-check.php
/ops/pre-ride-email-v3-live-package-export.php
/ops/pre-ride-email-v3-operator-approvals.php
/ops/pre-ride-email-v3-operator-approval-workflow.php
/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php
/ops/pre-ride-email-v3-adapter-contract-probe.php
```

## Next safest step

Build `v3.0.60-v3-live-adapter-kill-switch-check`.

Scope:

```text
read-only CLI + Ops page
prove whether live submit could run now
show every block reason
no live-submit enabling
no EDXEIX call
no AADE call
no queue mutation
no SQL schema change
no V0 changes
```
