# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

## Current patch

v3.2.0 adds **V3 Real Future Candidate Capture Readiness**.

This is a read-only readiness layer for detecting a real future possible-real pre-ride queue row before pickup expiry while the V3 live gate remains closed.

## What changed

New read-only CLI:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
```

New authenticated Ops page:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
```

Navigation updates:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
```

Documentation:

```text
/home/cabnet/docs/V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_20260515.md
```

## Current safe posture

- Production Pre-Ride Tool untouched.
- V0 workflow untouched.
- Live EDXEIX submit disabled.
- V3 live gate remains closed.
- No SQL changes.
- No DB writes.
- No queue mutations.
- No Bolt, EDXEIX, or AADE calls.
- No route moves/deletes/redirects.

## v3.2.0 expected output

When no real future candidate is visible:

```text
ok=true
version=v3.2.0-v3-real-future-candidate-capture-readiness
future_possible_real_rows=0
closed_gate_operator_review_candidates=0
operator_alerts_appropriate=0
live_risk_detected=false
final_blocks=[]
```

When a real future candidate appears, the tool should show:

```text
minutes_until_pickup_now
complete
missing_required_fields
qualifies_for_closed_gate_operator_review
urgent_about_to_expire
operator_alert_appropriate
```

## Next step

Upload v3.2.0, verify lint/CLI/web route, then continue watching for a real future possible-real pre-ride email before pickup expiry. Do not enable live submit.
