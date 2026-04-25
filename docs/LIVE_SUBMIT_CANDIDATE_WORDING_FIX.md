# Live Submit Candidate Wording Fix

This patch refines `/ops/live-submit.php` so historical or terminal Bolt rows are not presented as real future live-submit candidates.

## Purpose

The previous live-submit gate correctly blocked old finished trips, but the first-live-submit checklist could still say a selected analyzed booking existed. That wording was too easy to misunderstand.

This patch clarifies the distinction between:

- analyzed recent rows
- real future candidates
- live-eligible rows

## Safety posture

This patch remains read-only on GET and still cannot submit to EDXEIX.

It does not:

- call Bolt
- call EDXEIX
- write to the database on GET
- create jobs
- enable live submission
- implement live HTTP transport

## Expected current result

Until a real future Bolt ride exists, `/ops/live-submit.php` should show:

- analyzed recent rows may be greater than 0
- real future candidates = 0
- live-eligible rows = 0
- no selected booking by default
- no real future candidate is selected
- live HTTP transport is still blocked

Historical/finished/cancelled rows remain available in the analyzed rows table for review, but they are not selected automatically.
