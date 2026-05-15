# gov.cabnet.app patch — v3.1.6 V3 Next Real-Mail Candidate Watch navigation

## What changed

Navigation-only update to expose the read-only V3 Next Real-Mail Candidate Watch page from the shared operations shell.

Added route link:

- `/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php`

Added to:

- Pre-Ride top dropdown
- Daily Operations sidebar

No live behavior changes.

## Files included

- `public_html/gov.cabnet.app/ops/_shell.php`
- `docs/V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_NAV_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

- `public_html/gov.cabnet.app/ops/_shell.php`

To:

- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

grep -n "v3.1.6\|Next Candidate Watch\|next real-mail candidate watch navigation"   /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected:

- No syntax errors
- HTTP 302 to `/ops/login.php` when unauthenticated
- `v3.1.6` marker present
- `Next Candidate Watch` links present

## Safety

- Production Pre-Ride Tool untouched
- V0 workflow untouched
- No route moves/deletes/redirects
- No SQL changes
- No DB writes
- No queue mutations
- No Bolt calls
- No EDXEIX calls
- No AADE calls
- Live EDXEIX submission remains disabled
