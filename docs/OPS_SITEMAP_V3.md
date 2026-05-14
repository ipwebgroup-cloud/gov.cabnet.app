# gov.cabnet.app Ops Sitemap — V3 Pre-Ride Automation

This document defines the recommended operations-site structure for the Bolt pre-ride email V3 workflow.

Scope of this sitemap:

- Improve operator clarity.
- Keep monitoring pages grouped logically.
- Keep live-submit pages visibly locked.
- Avoid mixing AADE/receipt automation with pre-ride EDXEIX readiness.
- Preserve the current plain PHP / mysqli / cPanel workflow.

Live EDXEIX submission remains disabled.

---

## 1. Operations Home

Primary URL:

```text
/ops/index.php
```

Purpose:

- Main entry point.
- Production posture overview.
- Links to V3 automation, safety guards, mappings, Bolt bridge, receipts, diagnostics, and docs.
- Should clearly display:
  - Environment: production
  - Mode: dry-run / read-only
  - Live submit: disabled
  - Next action

Recommended top-level cards:

```text
V3 Pre-Ride Automation
Safety Guards
Live Submit Locked
Mappings & Reference Data
Bolt Bridge
Receipts & Notifications
Diagnostics
Documentation
```

---

## 2. V3 Pre-Ride Automation

Primary dashboard:

```text
/ops/pre-ride-email-v3-dashboard.php
```

Current key pages:

```text
/ops/pre-ride-email-v3-queue-watch.php
/ops/pre-ride-email-v3-fast-pipeline-pulse.php
/ops/pre-ride-email-v3-fast-pipeline.php
/ops/pre-ride-email-v3-automation-readiness.php
/ops/pre-ride-email-v3-queue.php
/ops/pre-ride-email-v3-cron-health.php
```

Purpose:

- Monitor pre-ride email intake.
- Confirm V3 queue row creation.
- Confirm pipeline/pulse status.
- Confirm whether rows reach:
  - `submit_dry_run_ready`
  - `live_submit_ready`
  - `blocked`

Recommended operator view:

```text
Current Test
Queue Snapshot
Pulse Runner Status
Latest Queue Rows
Next Action
```

---

## 3. Safety Guards

Pages:

```text
/ops/pre-ride-email-v3-starting-point-guard.php
/ops/pre-ride-email-v3-expiry-guard.php
/ops/pre-ride-email-v3-live-readiness.php
/ops/pre-ride-email-v3-live-payload-audit.php
```

Purpose:

- Validate lessor.
- Validate starting point.
- Block invalid, expired, past, cancelled, terminal, or unsafe rows.
- Audit future EDXEIX payloads before any future live adapter is allowed.

Known verified starting-point correction:

```text
lessor 2307
1455969 = ΧΩΡΑ ΜΥΚΟΝΟΥ
9700559 = ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ
invalid old value: 6467495
```

---

## 4. Live Submit Locked

Pages:

```text
/ops/pre-ride-email-v3-live-submit.php
/ops/pre-ride-email-v3-live-submit-gate.php
```

Future/installed pages may also include:

```text
operator approval gate
adapter contract probe
final rehearsal
```

Required display posture:

```text
Live submit: disabled
Master gate: closed
Operator approval: required
Adapter: disabled
Mode: disabled
```

Rule:

Live submit must remain blocked unless all of the following are true:

1. A real eligible future Bolt trip exists.
2. All preflights pass.
3. The trip is sufficiently in the future.
4. The disabled server-only config is intentionally changed.
5. The master gate is opened.
6. Operator approval is explicitly granted.
7. Andreas explicitly approves live-submit work.

---

## 5. Bolt Bridge

Legacy/current bridge pages:

```text
/ops/bolt-live.php
/ops/jobs.php
/ops/readiness.php
/ops/submit.php
/bolt-fleet-orders-watch.php
/bolt_sync_orders.php
/bolt_sync_reference.php
/bolt_edxeix_preflight.php
/bolt_jobs_queue.php
```

Purpose:

- Preserve existing bridge visibility.
- Keep legacy/current pages accessible.
- Avoid confusing them with the new V3 pre-ride email flow.

---

## 6. Mappings & Reference Data

Recommended future pages:

```text
/ops/mappings.php
/ops/mappings-drivers.php
/ops/mappings-vehicles.php
/ops/mappings-lessors.php
/ops/mappings-starting-points.php
/ops/vehicle-exemptions.php
```

Purpose:

- Show driver mappings.
- Show vehicle mappings.
- Show lessor mappings.
- Show verified EDXEIX starting-point options.
- Show permanent vehicle exemptions.

Known exemption:

```text
Vehicle: EMT8640
Bolt vehicle identifier: f9170acc-3bc4-43c5-9eed-65d9cadee490
Status: permanently exempt
```

Exemption rule:

```text
No voucher
No driver email
No invoice / AADE receipt
No EDXEIX worker submission
No V3 queue intake
```

---

## 7. Receipts & Notifications

Recommended future pages:

```text
/ops/receipts.php
/ops/receipt-auto-issuer-status.php
/ops/driver-email-notifications.php
/ops/voucher-tools.php
/ops/receipt-duplicate-protection.php
```

Purpose:

- Keep AADE receipt automation separate from pre-ride EDXEIX automation.
- Show post-ride fiscal/notification operations.
- Preserve duplicate protection visibility.

---

## 8. Diagnostics

Recommended pages/tools:

```text
/ops/diagnostics.php
/ops/pre-ride-email-v3-cron-health.php
/ops/pre-ride-email-v3-fast-pipeline-pulse.php
/bolt_readiness_audit.php
/bolt_edxeix_preflight.php?limit=30
/bolt_jobs_queue.php?limit=50
```

Future diagnostics:

```text
Recent V3 errors
Blocked row inspector
Schema/version check
Config visibility check
Pulse runner log summary
Cron posture summary
```

Rules:

- Redact secrets.
- Do not expose raw logs publicly unless sanitized.
- Do not expose session files, cookies, tokens, or credentials.

---

## 9. Documentation

Recommended docs area:

```text
/ops/docs.php
```

Important files:

```text
HANDOFF.md
CONTINUE_PROMPT.md
README.md
DEPLOYMENT.md
SECURITY.md
SCOPE.md
PROJECT_FILE_MANIFEST.md
docs/
```

Purpose:

- Keep project continuity strong.
- Make handoff and deployment state easy to find.

---

## Recommended Navigation Labels

```text
Operations
V3 Automation
Safety Guards
Live Submit Locked
Mappings
Bolt Bridge
Receipts
Diagnostics
Docs
```

---

## Recommended UI Status Colors

```text
Green  = safe / ready / OK
Amber  = waiting / caution / manual check
Red    = blocked / disabled / error / dangerous if enabled
Blue   = informational
Gray   = inactive
```

---

## Current First Implementation

Patch `v3.0.36-ops-ui-sitemap-dashboard` adds:

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
docs/OPS_SITEMAP_V3.md
PATCH_README.md
```

This first step is additive. It does not modify existing V3 workers, crons, gates, queue logic, mappings, or SQL.

---

## v3.0.38 UI Coherence Update

The Ops Home page visual language is now the canonical shell for the V3 dashboard.

The V3 dashboard should visually match:

```text
white top navigation
EA/gov.cabnet.app brand area
deep-blue sidebar
light gray page background
white metric and content cards
simple tab row below the title
clear green/amber/red status badges
```

The V3 dashboard must not become a separate visual system. Future V3 pages should gradually adopt the same shared shell after each page is verified.

Patch `v3.0.38-ops-shell-unify-v3-dashboard` updates:

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
docs/OPS_UI_STYLE_NOTES.md
docs/OPS_SITEMAP_V3.md
PATCH_README.md
```

No live-submit behavior, cron behavior, queue mutation, SQL, or mapping change is included.
