# V3 Closed-Gate Adapter Diagnostics

Version: `v3.0.54-v3-closed-gate-adapter-diagnostics`

## Purpose

This patch adds a V3-only diagnostic layer for the future live-submit adapter path.

It answers one question safely:

> If a V3 queue row were considered for live submission, what would still block it before any EDXEIX call?

## New CLI

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php
```

Optional:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php --queue-id=56
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php --queue-id=56 --json
```

## New Ops page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php
```

## What it checks

- Master live-submit gate config loaded/readable.
- Gate flags: enabled, mode, adapter, hard-enable, acknowledgement phrase.
- Selected adapter file presence.
- V3 queue selected row.
- Required payload fields.
- Operator-verified starting point.
- Operator approval table and row approval state.
- V3 live package exporter presence.
- Existing local artifacts for the selected queue row.
- Final reasons why live submit remains blocked.

## Safety boundary

This diagnostic does **not**:

- call Bolt;
- call EDXEIX;
- call AADE;
- modify queue rows;
- write production submission tables;
- change cron schedules;
- enable live submit;
- touch the V0 laptop/manual production helper.

## Expected current result

Current V3 live submit should remain blocked by the master gate:

```text
enabled is false
mode is not live
adapter is disabled
hard_enable_live_submit is false
required acknowledgement phrase is not present
approval: no valid operator approval found
```

This is correct until Andreas explicitly approves a live-submit gate-opening phase.
