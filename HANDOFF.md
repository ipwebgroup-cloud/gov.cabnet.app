# gov.cabnet.app V3 Handoff — v3.2.14

Current state: controlled one-shot Maildir fixture writer added, preview-only by default.

Safety:
- Live EDXEIX submit remains disabled.
- Production Pre-Ride Tool remains untouched.
- No DB writes or queue mutations are made by preview mode.
- Writer creates exactly one Maildir message only if explicit write flag and confirmation phrase are both provided.
- No Bolt, EDXEIX, or AADE calls.

Primary verification command:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-fixture-writer-json
```
