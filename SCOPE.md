# Scope — gov.cabnet.app Bolt → EDXEIX Bridge

## Current ASAP track

Move toward safe EDXEIX automation while preserving Production V0.

## v3.2.36 scope

- Fetch authenticated EDXEIX create form immediately before a supervised one-shot POST.
- Extract fresh hidden `_token` internally only.
- Inject fresh token into the existing one-shot transport trace.
- Keep candidate 4 closed/manual V0 submitted and retry-blocked.
- Require explicit candidate ID, exact payload hash, exact confirmation phrase, no previous server attempt, no closure, future guard, and valid create-form context.

## Out of scope

- No unattended automation.
- No cron.
- No AADE/myDATA call.
- No queue job.
- No normalized_bookings write.
- No live config write.
- No V0 production changes.
