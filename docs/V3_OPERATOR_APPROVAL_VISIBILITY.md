# V3 Operator Approval Visibility

Version: `v3.0.53-v3-operator-approval-visibility`

This patch adds a read-only operator approval visibility page for the V3 Bolt pre-ride email automation path.

## Page

```text
/ops/pre-ride-email-v3-operator-approvals.php
```

## Purpose

The page exposes approval-related state without making operational decisions or changing data.

It shows:

- V3 queue rows
- Current queue status
- Pickup timing
- Driver / vehicle / lessor / starting-point IDs
- Whether an approval table exists
- Latest approval records if present
- Approval-like records associated with each queue row
- Master gate config state
- Master gate block reasons

## Safety boundary

This patch does not:

- call Bolt
- call EDXEIX
- call AADE
- create approvals
- approve rows
- change queue status
- write production submission tables
- touch V0 laptop/manual helper files
- enable live submit
- change SQL schema

## Current phase

The V3 forwarded-email readiness path is proven, and local live package export is proven. The next live-submit preparation layer is operator approval visibility behind the closed master gate.

Live submit remains disabled.
