# HANDOFF — gov.cabnet.app V3 Automation

Current checkpoint: `v3.0.63-v3-pre-live-switchboard`

## Verified state

- V3 intake/parser/mapping proven.
- `submit_dry_run_ready` proven.
- `live_submit_ready` proven.
- Payload audit proven.
- Package export proven.
- Operator approval workflow proven.
- Final rehearsal accepts valid closed-gate approval and blocks on master gate.
- Adapter skeleton installed and non-live-capable.
- Adapter contract probe proven.
- Kill-switch checker aligned with approval logic.
- Pre-live switchboard added.
- Live submit remains disabled.
- V0 laptop/manual helper remains untouched.

## Critical safety boundary

Do not enable live submit unless Andreas explicitly requests a live-submit gate-opening update.

Live submit must remain blocked unless all of these are true:

- real eligible future Bolt trip
- row is `live_submit_ready`
- pickup still sufficiently future-safe
- verified starting point
- payload audit OK
- package export OK
- valid operator approval
- final rehearsal passes except deliberately opened master gate controls
- adapter is intentionally made live-capable
- config enabled=true
- mode=live
- adapter=edxeix_live
- hard_enable_live_submit=true
- acknowledgement phrase present

## Latest added files

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
```

## Verification command

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php"
```
