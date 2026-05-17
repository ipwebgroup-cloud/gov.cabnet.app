Continue gov.cabnet.app Bolt → EDXEIX bridge from v3.2.37.

Stack: plain PHP/mysqli/MariaDB/cPanel/manual upload. No frameworks/build tools.

Latest validated state:
- v3.2.36 fresh EDXEIX create-form token integration works.
- Candidate 4: manually submitted via V0/laptop; closed and retry-blocked.
- Candidate 5: one server-side POST attempt; redirected to create form; not confirmed saved; do not retry.
- v3.2.37 patch adds strict identity lock and sanitized validation capture.

Rules:
- V0 production must remain untouched.
- No unattended automation or cron.
- No AADE calls from EDXEIX tests.
- Never POST without explicit candidate_id, expected customer, expected driver, expected vehicle, expected pickup datetime, expected payload hash, and exact confirmation phrase.
- If EDXEIX redirects back to create form, treat as validation/not-confirmed and verify manually.
