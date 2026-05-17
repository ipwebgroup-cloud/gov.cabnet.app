Continue gov.cabnet.app Bolt → EDXEIX bridge from v3.2.32.

Priority: keep V0 production untouched. Candidate 4 was manually submitted through V0/laptop after server-side v3.2.30 POST returned HTTP 419/session expired. v3.2.31 added closure/retry prevention but manual closure failed because submitted_at was empty. v3.2.32 fixes the closure timestamp default.

Next: verify v3.2.32, mark candidate 4 as manual_submitted_v0, confirm server retry is blocked, then work on EDXEIX fresh form-token/session diagnostics.
