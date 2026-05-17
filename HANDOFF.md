# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current patch: v3.2.36 — Fresh EDXEIX create-form token transport integration.

## Current state

- Production V0 laptop/manual EDXEIX workflow remains operational and untouched.
- Candidate 4 was manually submitted through V0/laptop and archived as `manual_submitted_v0`.
- Server-side retry for candidate 4 is blocked by closure/retry-prevention.
- v3.2.35 proved the saved server session can reach `/dashboard/lease-agreement/create`, select the real lease-agreement form, and detect a matching hidden `_token`.
- v3.2.36 fetches that fresh create-form `_token` immediately before a supervised one-shot POST and uses it internally only; it is never printed or stored.

## Safety

No unattended automation, no cron, no AADE/myDATA call, no queue job, no normalized_bookings write, no live_submit.php write, and no V0 production changes.

## Next safest step

1. Save current EDXEIX browser session from the browser extension.
2. Verify token readiness:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

3. Capture a new future pre-ride candidate.
4. Dry-run explicit candidate ID:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=N --json
```

5. Only if `PRE_RIDE_TRANSPORT_TRACE_ARMABLE`, run one supervised POST with exact hash and exact confirmation phrase.

Do not use candidate 4 again.
