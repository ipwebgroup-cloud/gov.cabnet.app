# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge v3.2.37

Current state:
- V0/laptop production workflow remains operational and untouched.
- Candidate 4 was manually submitted via V0/laptop and closed as `manual_submitted_v0`.
- Candidate 5 received one server-side POST attempt with fresh token; it redirected back to `/dashboard/lease-agreement/create` and is not confirmed saved. It must not be retried.
- Server-side EDXEIX create-form session/token is valid after saving the browser session.
- v3.2.37 adds strict identity lock and validation capture before any further supervised POST.

Critical rule:
- Never POST from latest-ready/latest-mail selection alone.
- Always select explicit candidate_id.
- Always require expected customer, driver, vehicle, pickup datetime, payload hash, and exact confirmation phrase.
- Do not retry candidate 4 or candidate 5.

Next safe workflow:
1. Capture new future pre-ride email.
2. List recent candidates.
3. Select explicit candidate ID.
4. Dry-run.
5. POST only if identity lock matches the intended ride exactly.
