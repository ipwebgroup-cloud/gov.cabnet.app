# gov.cabnet.app Handoff — 2026-05-17 v3.2.23 ASAP Automation Track

Current posture:

- Stack remains plain PHP + mysqli/MariaDB with cPanel/manual upload workflow.
- Production V0 remains unaffected.
- EDXEIX live submission remains blocked unless Andreas explicitly authorizes a supervised one-shot diagnostic.
- v3.2.21 candidate diagnostics were validated: no stale booking selected, no transport performed, +30 minute future guard active.
- Server config now has `future_start_guard_minutes => 30` in both `bolt.php` and `config.php`.
- v3.2.22 pre-ride future candidate path was installed and syntax-validated.
- Additive table `edxeix_pre_ride_candidates` was installed successfully after the first command with placeholder DB credentials failed and the real DB command succeeded.
- `--write=1` captured sanitized candidate metadata as candidate_id `1`; raw email body was not stored.
- First v3.2.22 Maildir test loaded a message but the primary parser returned empty fields, so the candidate was correctly blocked.

v3.2.23 next patch:

- Adds a diagnostics-only fallback label parser inside `edxeix_pre_ride_candidate_lib.php`.
- Leaves `BoltPreRideEmailParser.php` untouched to avoid changing production V0/manual pre-ride behavior.
- Adds `candidate.parser_fallback` diagnostics to show whether fallback parsing was used.
- Still performs no EDXEIX HTTP transport, AADE call, queue job, or normalized booking write.

Next safest verification:

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1
```
