# gov.cabnet.app — V3 Observation Overview Navigation

Date: 2026-05-15
Version: v3.1.10-v3-observation-overview-navigation

## Purpose

Adds navigation access for the read-only V3 Real-Mail Observation Overview introduced in v3.1.9.

The overview consolidates:

- V3 Real-Mail Queue Health
- V3 Real-Mail Expiry Reason Audit
- V3 Next Real-Mail Candidate Watch

## Safety posture

This is a navigation/text-only patch.

- No Bolt calls
- No EDXEIX calls
- No AADE calls
- No database writes
- No queue mutations
- No route moves
- No route deletes
- No redirects
- Live EDXEIX submit remains disabled
- Production Pre-Ride Tool remains untouched

## Verified prior state

The v3.1.9 observation overview was verified on production with:

- ok=true
- queue_ok=true
- expiry_ok=true
- watch_ok=true
- future_active=0
- operator_candidates=0
- live_risk=false
- final_blocks=[]

## Files changed

- public_html/gov.cabnet.app/ops/_shell.php

## Expected verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-observation-overview.php
grep -n "v3.1.10\|V3 Observation Overview\|real-mail observation overview navigation" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected result:

- PHP syntax clean
- HTTP 302 to /ops/login.php when unauthenticated
- v3.1.10 marker present
- V3 Observation Overview navigation links present
