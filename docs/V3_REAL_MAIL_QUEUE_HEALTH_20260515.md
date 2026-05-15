# V3 Real-Mail Intake + Queue Health — 2026-05-15

## Purpose

This patch adds a read-only V3 operational health board for observing the next real Bolt pre-ride email intake while the live EDXEIX gate remains closed.

## Safety contract

- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No database writes.
- No queue status changes.
- No filesystem writes.
- No route moves, deletions, redirects, or live-submit enablement.
- Production `/ops/pre-ride-email-tool.php` remains untouched.

## Added routes

- CLI: `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php`
- Ops: `https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php`

## What the audit reports

- V3 queue table presence.
- Latest queue row classification.
- Possible real-mail rows versus generated canary rows.
- Future active rows.
- Live-submit-ready and dry-run-ready counts.
- Past ready rows that must not be submitted.
- Stale locked rows.
- Missing required field rows.
- Live gate posture and closed-gate risk check.

## Expected posture

The live submit config should remain closed:

- `enabled=false`
- `mode=disabled`
- `adapter=disabled`
- `hard_enable_live_submit=false`

No live EDXEIX submission is approved by this patch.
