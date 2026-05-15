# HANDOFF — gov.cabnet.app V3 Observation Overview Navigation

Current milestone: v3.1.10 V3 Observation Overview navigation.

## Installed/verified before this patch

v3.1.9 V3 Real-Mail Observation Overview was verified on production:

```text
ok=true
version=v3.1.9-v3-real-mail-observation-overview
queue_ok=true
expiry_ok=true
watch_ok=true
future_active=0
operator_candidates=0
live_risk=false
final_blocks=[]
```

## This patch

Adds navigation links for:

```text
/ops/pre-ride-email-v3-observation-overview.php
```

in:

- Pre-Ride top dropdown
- Daily Operations sidebar

## Safety

Navigation/text only. No Bolt/EDXEIX/AADE calls. No DB writes. No queue mutations. No route moves/deletes/redirects. Live submit remains disabled.
