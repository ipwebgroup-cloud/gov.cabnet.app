# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current checkpoint: V3 closed-gate real-mail observation continues.

Latest patch: v3.1.5 adds a read-only V3 Next Real-Mail Candidate Watch.

The watcher reports future possible-real rows and operator review candidates before pickup expires. It does not write to the database, change queue rows, call Bolt, call EDXEIX, call AADE, or enable live submission.

Safety posture remains unchanged:

- Production Pre-Ride Tool untouched.
- V0 workflow untouched.
- No SQL changes.
- No queue mutations.
- No routes moved, deleted, or redirected.
- Live EDXEIX submit remains disabled.
- V3 live gate remains closed.

Verification command:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "future_possible=".($j["summary"]["future_possible_real_rows"]??"?").PHP_EOL; echo "operator_candidates=".($j["summary"]["operator_review_candidates"]??"?").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

Next safest step after verification: add navigation for the watcher as a separate tiny `_shell.php` patch, or continue observing until a real future Bolt pre-ride email arrives.
