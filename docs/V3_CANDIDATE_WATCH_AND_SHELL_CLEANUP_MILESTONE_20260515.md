# gov.cabnet.app — V3 Candidate Watch + Shell Cleanup Milestone

Date: 2026-05-15

## Scope

This documentation checkpoint covers the V3 closed-gate observation work from v3.1.5 through v3.1.8.

Covered changes:

- v3.1.5 — V3 Next Real-Mail Candidate Watch
- v3.1.6 — Navigation for V3 Next Real-Mail Candidate Watch
- v3.1.8 — Shared ops shell side-note typo cleanup

v3.1.7 was superseded by the corrected v3.1.8 shell cleanup after live verification showed the side-note text had not changed as intended.

## Verified production state

Latest verified V3 Next Real-Mail Candidate Watch output:

```text
ok=true
version=v3.1.5-v3-next-real-mail-candidate-watch
future_possible=0
operator_candidates=0
live_risk=false
final_blocks=[]
```

Latest verified shared shell cleanup state:

```text
_shell.php syntax: PASS
Auth redirect for /ops/pre-ride-email-v3-next-real-mail-candidate-watch.php: PASS
shared operations UI shell v3.1.8 marker: PRESENT
legacy stats source audit navigation wording: PRESENT
next real-mail candidate watch navigation added in v3.1.6 wording: PRESENT
bad token legacystats: ABSENT
bad token inv3.1.6: ABSENT
bad token utilityrelocation: ABSENT
bad token healthnavigation: ABSENT
bad token navigationadded: ABSENT
public _shell.php.bak_v3_1_8* files: REMOVED
```

## Safety posture

The entire milestone remained observation-only and closed-gate.

- Production Pre-Ride Tool `/ops/pre-ride-email-tool.php`: untouched
- V0 workflow: untouched
- Routes moved/deleted/redirected: none
- SQL changes: none
- Bolt calls: none
- EDXEIX calls: none
- AADE calls: none
- DB writes: none
- Queue mutations: none
- Live EDXEIX submit: disabled
- V3 live gate: closed

## Operational meaning

The V3 candidate watch is now available to help operators detect the next future possible-real Bolt pre-ride email row before it expires.

At the time of verification there were no future possible-real rows and no operator candidates:

```text
future_possible=0
operator_candidates=0
```

This means no row was eligible for live EDXEIX submission, and no live submit action was recommended.

## Files involved in this milestone

V3 Next Real-Mail Candidate Watch:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php
```

Shared shell navigation / note cleanup:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "future_possible=".($j["summary"]["future_possible_real_rows"]??"?").PHP_EOL; echo "operator_candidates=".($j["summary"]["operator_review_candidates"]??"?").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

## Commit reference

Recommended commit title:

```text
Document V3 candidate watch milestone
```

Recommended commit description:

```text
Documents the v3.1.5–v3.1.8 V3 candidate watch and shared shell cleanup milestone.

Records the read-only V3 Next Real-Mail Candidate Watch, navigation availability, and corrected v3.1.8 shared shell side-note cleanup.

Confirms the verified closed-gate state: future_possible=0, operator_candidates=0, live_risk=false, final_blocks empty, auth protection intact, and no public backup files remaining.

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database write, queue mutation, or filesystem write actions are performed.

Live EDXEIX submission remains disabled, the V3 live gate remains closed, and the production pre-ride tool remains untouched.
```
