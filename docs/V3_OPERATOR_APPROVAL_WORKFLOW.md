# V3 Operator Approval Workflow

Version: v3.0.58-v3-operator-approval-workflow

This patch adds a controlled operator approval workflow for V3 closed-gate rehearsal only.

## Safety boundary

- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No queue status changes.
- No production submission table writes.
- No live-submit gate changes.
- V0 laptop/manual helper remains untouched.

## Runtime files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_operator_approval.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-operator-approval-workflow.php`

## Required approval phrase

```text
I APPROVE V3 ROW FOR CLOSED-GATE REHEARSAL ONLY
```

## Required revoke phrase

```text
I REVOKE V3 ROW APPROVAL
```

## Approval command

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_operator_approval.php --queue-id=QUEUE_ID --approve --phrase=\"I APPROVE V3 ROW FOR CLOSED-GATE REHEARSAL ONLY\" --approved-by=\"Andreas\" --minutes=15"
```

## Revoke command

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_operator_approval.php --queue-id=QUEUE_ID --revoke --phrase=\"I REVOKE V3 ROW APPROVAL\" --approved-by=\"Andreas\""
```

## Eligibility rules

A row can be approved only when all are true:

- queue row exists
- `queue_status = live_submit_ready`
- pickup is still in the future
- required payload/mapping fields are present
- starting point is operator-verified in `pre_ride_email_v3_starting_point_options`
- approval table exists
- approval phrase matches exactly

The approval does not open live submit. It only removes the approval-layer block for closed-gate rehearsal diagnostics.
