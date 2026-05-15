# V3.2.0 — Real Future Candidate Capture Readiness

## Purpose

Adds a read-only V3 capture-readiness layer for the next real future possible-real pre-ride queue row.

The goal is to catch and inspect a real eligible future Bolt pre-ride email before pickup expiry while the V3 live gate remains closed.

## What it shows

- Whether any future possible-real row exists.
- Minutes until pickup.
- Whether the row is complete.
- Missing required fields, if any.
- Whether parser, mapping, and future-safety flags are OK.
- Whether the row qualifies for closed-gate operator review.
- Whether the row is urgent or about to expire.
- Whether an operator alert would be appropriate.
- Whether the V3 live gate is still safely closed.

## Safety posture

This patch is read-only.

It performs:

- no Bolt calls,
- no EDXEIX calls,
- no AADE calls,
- no DB writes,
- no queue status changes,
- no filesystem writes,
- no route moves,
- no route deletes,
- no redirects,
- no live-submit enablement.

The production Pre-Ride Tool remains untouched:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## New files

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
```

## Updated files

```text
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/_ops-nav.php
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## CLI verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --json
```

Expected JSON posture when no real future candidate exists:

```text
ok=true
version=v3.2.0-v3-real-future-candidate-capture-readiness
future_possible_real_rows=0
closed_gate_operator_review_candidates=0
operator_alerts_appropriate=0
live_risk_detected=false
live_submit_recommended_now=0
db_write_made=false
queue_mutation_made=false
bolt_call_made=false
edxeix_call_made=false
aade_call_made=false
final_blocks=[]
```

If a real future possible-real row appears, expected safe behavior is:

```text
future_possible_real_rows > 0
minutes_until_pickup_now visible
missing_required_fields visible
qualifies_for_closed_gate_operator_review true/false visible
operator_alert_appropriate true/false visible
live_submit_recommended_now=0
```

## Web verification

Unauthenticated route check:

```bash
curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
```

Expected:

```text
HTTP 302 to /ops/login.php?next=%2Fops%2Fpre-ride-email-v3-real-future-candidate-capture-readiness.php
```

Authenticated visual check:

```text
/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
```

Expected:

- Read-only safety banner visible.
- Version badge shows v3.2.0.
- Metrics show future possible-real rows, complete future rows, closed-gate review candidates, operator alerts, urgent/about-to-expire rows, and live risk.
- Tables show the next future possible-real row, review candidates, alert rows, future possible-real rows, and latest rows scanned.

## Interpretation

This patch does not make the system ready for unattended automation. It improves the ability to capture proof from the next real future pre-ride email before the pickup window expires.

Live EDXEIX submission remains disabled until Andreas explicitly asks for a live-submit update and all live gates pass.
