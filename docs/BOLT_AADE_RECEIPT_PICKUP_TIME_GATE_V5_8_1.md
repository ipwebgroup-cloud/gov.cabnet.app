# v5.8.1 — AADE Receipt Pick-up Time Gate

## Purpose

Delays automatic AADE/myDATA receipt issuance and the official receipt email until the Bolt mail booking pick-up time has been reached.

For `source='bolt_mail'` normalized bookings, `normalized_bookings.started_at` is the parsed Bolt estimated pick-up time. The earlier Bolt email `Start time` remains ride context only and is not used as the receipt send trigger.

## Behavior

Before pick-up time:

- AADE SendInvoices is not called.
- Official receipt PDF/email is not sent.
- The auto worker reports blocker `pickup_time_not_reached`.

At or after pick-up time:

- Existing v5.8 gates still apply.
- AADE SendInvoices can be attempted for eligible new real Bolt mail bookings.
- Official receipt PDF can be emailed to the driver only after AADE issuance succeeds.

## Safety retained

- `auto_issue_not_before` still blocks old rows.
- Duplicate protection by booking and XML hash remains.
- Test/synthetic rows remain blocked.
- Generated/static receipt fallback remains off.
- EDXEIX is not called.
- `submission_jobs` and `submission_attempts` are not created.
