# Scope — gov.cabnet.app Bolt → EDXEIX Bridge

## Current ASAP track

Move toward safe EDXEIX automation while preserving Production V0.

## v3.2.35 scope

- Read-only create-form token diagnostic classification fix.
- Select the actual lease-agreement form instead of the logout form.
- Accept EDXEIX field aliases: `starting_point_id` for starting point and `lessee[name]`/`lessee[type]` for lessee.
- Treat generic login/CSRF text signals as informational if the authenticated create form, token, and expected fields are present.

## Out of scope

- No EDXEIX POST.
- No AADE/myDATA call.
- No queue job.
- No normalized_bookings write.
- No live config write.
- No V0 production changes.
