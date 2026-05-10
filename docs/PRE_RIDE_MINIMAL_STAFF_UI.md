# Pre-Ride Minimal Staff UI — v6.6.16

This patch adds a simple non-technical interface on top of the existing `/ops/pre-ride-email-tool.php` workflow.

It does not replace the parser, DB lookup, Firefox helper, EDXEIX page, AADE logic, queues, or submission safety gates.

## Staff workflow

1. Open `/ops/pre-ride-email-tool.php?v=6616`.
2. Click **Load latest email + check IDs**.
3. Confirm status says **IDs READY**.
4. Click **Save + open EDXEIX**.
5. On EDXEIX, click **Fill using exact IDs**.
6. Verify every visible field.
7. Select/click the exact pickup point on the EDXEIX map.
8. Save only if the trip is future.

## Safety

- No AADE calls.
- No EDXEIX server-side calls from gov.cabnet.app.
- No DB writes from the UI layer.
- No queue jobs.
- No auto-submit.
- The existing Firefox helper remains responsible for local browser-side filling and live safety blocks.
