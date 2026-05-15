# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Date: 2026-05-15

## Current milestone

V3 closed-gate observation has progressed through v3.1.8.

Recent verified milestone:

- v3.1.5 — V3 Next Real-Mail Candidate Watch
- v3.1.6 — Candidate watch navigation
- v3.1.8 — Shared ops shell side-note cleanup after v3.1.7 was found incomplete

## Latest verified live outputs

V3 Next Real-Mail Candidate Watch:

```text
ok=true
version=v3.1.5-v3-next-real-mail-candidate-watch
future_possible=0
operator_candidates=0
live_risk=false
final_blocks=[]
```

Shared ops shell cleanup:

```text
_shell.php syntax: PASS
Auth redirect: PASS
v3.1.8 marker: PRESENT
legacy stats source audit navigation wording: PRESENT
next real-mail candidate watch navigation added in v3.1.6 wording: PRESENT
Bad typo tokens: ABSENT
Public _shell.php.bak_v3_1_8* backups: REMOVED
```

## Safety posture

- Production Pre-Ride Tool `/ops/pre-ride-email-tool.php`: untouched
- V0 workflow: untouched
- Live EDXEIX submit: disabled
- V3 live gate: closed
- Routes moved/deleted/redirected: none
- SQL changes: none
- Bolt calls: none
- EDXEIX calls: none
- AADE calls: none
- DB writes: none
- Queue mutations: none

## Next safest step

Continue closed-gate V3 observation. Watch for a real future possible-real Bolt pre-ride row using:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php --json"
```

If a future candidate appears, inspect it with read-only tooling only. Do not enable live EDXEIX submission unless Andreas explicitly requests a live-submit update.
