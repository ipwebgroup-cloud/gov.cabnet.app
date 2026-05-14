You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Continue from V3 closed-gate pre-live automation.

Project constraints:
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.
- No frameworks, Composer, Node, or heavy dependencies.
- Live server is not a cloned Git repo.
- Patch zips must mirror the live/repo structure directly, with no wrapper folder.
- V0 production/manual helper must remain untouched.

Current V3 status:
- Intake/pulse path proven.
- Future-safe rows reached `live_submit_ready`.
- Operator approval workflow proven.
- Package export proven.
- Final rehearsal accepted valid approval and blocked only on master gate during proof.
- Kill-switch accepted valid approval and blocked on master gate/adapter during proof.
- Pre-live switchboard browser page works with direct DB/config read-only renderer.
- Adapter row simulation proven.
- Future adapter skeleton is present, non-live-capable, and returns `submitted=false`.
- Live submit remains disabled.

Latest planned/created patch:
`v3.0.69-v3-adapter-payload-consistency-harness`

It adds:
- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php`

Purpose:
Compare DB-built EDXEIX fields, latest package export edxeix_fields artifact, and future adapter skeleton payload hash for the selected V3 row.

Verification:
```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --json"
```

Expected safety:
- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No DB writes.
- No queue status changes.
- No production submission table writes.
- V0 untouched.

If verified, prepare the commit summary. If it fails, patch only the V3 harness/page.
