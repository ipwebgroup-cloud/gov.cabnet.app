You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Continue from the current V3 closed-gate real-mail observation state.

Latest patch: v3.1.5 V3 Next Real-Mail Candidate Watch.

Files:
- /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php
- /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

Purpose:
- Read-only watcher for the next future possible-real V3 pre-ride email queue row before it expires.
- Highlights operator review candidates only.
- Does not submit or mutate anything.

Critical safety:
- Do not enable live EDXEIX submission unless Andreas explicitly requests a live-submit update.
- Production Pre-Ride Tool remains untouched.
- V0 remains untouched.
- No SQL changes.
- No DB writes.
- No queue mutations.
- No Bolt, EDXEIX, or AADE calls.

Verification:
- PHP syntax clean on both files.
- curl should return 302 to /ops/login.php.
- CLI JSON should show live_risk=false and final_blocks=[].

Next safe action:
- If v3.1.5 verifies cleanly, add navigation for the watcher in a separate `_shell.php` patch.
- If a real future row appears, inspect it with closed-gate tools only. Do not submit.
