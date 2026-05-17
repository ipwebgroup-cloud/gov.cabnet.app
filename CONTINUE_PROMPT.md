Continue gov.cabnet.app Bolt → EDXEIX bridge from v3.2.34.

Source of truth:
1. Latest terminal/browser outputs pasted by Andreas.
2. HANDOFF.md.
3. Current uploaded patch files.

Current status:
- Pre-ride email parsing/capture/readiness works.
- Candidate 4 real ride was submitted manually via V0/laptop after server POST returned HTTP 419/session expired.
- Candidate 4 is archived/manual_submitted_v0 and server retry is blocked.
- v3.2.33 showed server-side EDXEIX create-form GET redirects to public/root page, no form/token.
- v3.2.34 adds browser create-form proof validation.

Next action:
- Validate browser proof JSON from the real logged-in EDXEIX create page.
- Do not build any live POST patch unless Andreas explicitly approves.
- Keep V0 production untouched.
