# gov.cabnet.app v6.6.2 Patch — Manual Bolt Pre-Ride Email Utility

## What changed

Adds a simple manual utility so operations can paste a Bolt pre-ride email and immediately get an editable operator form with extracted transfer fields.

This keeps the business functioning while the guarded normalized automation continues later.

## Safety

This patch is read-only/manual assistance only:

- No DB access.
- No DB writes.
- No network calls.
- No Bolt API calls.
- No EDXEIX calls.
- No AADE calls.
- No queue jobs.
- No submission attempts.
- No email body storage.

## Files included

```text
gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
gov.cabnet.app_app/cli/parse_pre_ride_email.php
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
docs/BOLT_PRE_RIDE_EMAIL_UTILITY.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

Upload these files to:

```text
gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php

gov.cabnet.app_app/cli/parse_pre_ride_email.php
→ /home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php

public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php

docs/BOLT_PRE_RIDE_EMAIL_UTILITY.md
→ local GitHub repo docs/BOLT_PRE_RIDE_EMAIL_UTILITY.md

HANDOFF.md
→ local GitHub repo HANDOFF.md

CONTINUE_PROMPT.md
→ local GitHub repo CONTINUE_PROMPT.md
```

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
php -l /home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

Optional CLI test:

```bash
cat >/tmp/bolt-email-test.txt <<'EOF'
Operator: Fleet Mykonos LUXLIMO IKE
Customer: Example Customer
Customer mobile: +306900000000
Driver: Example Driver
Vehicle: ABC1234
Pickup: Mikonos 846 00, Greece
Drop-off: Mykonos Airport, Greece
Start time: 2026-05-10 18:10:00 EEST
Estimated pick-up time: 2026-05-10 18:15:00 EEST
Estimated end time: 2026-05-10 18:40:00 EEST
Estimated price: €60.00
EOF

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php --file=/tmp/bolt-email-test.txt --json
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

Expected result:

- Page opens with noindex/no-cache headers.
- Pasted pre-ride email parses into an editable form.
- Missing fields/warnings are shown clearly.
- Copy buttons work for fields, dispatch summary, and CSV row.
- No database rows, jobs, attempts, AADE receipts, or EDXEIX calls are created.

## Git commit title

```text
Add manual Bolt pre-ride email parser utility
```

## Git commit description

```text
Adds a safe manual operations utility for parsing Bolt pre-ride email bodies into an editable operator form.

Includes a private reusable parser class, a public ops page, a CLI parser helper, documentation, and updated handoff/continue prompts.

The utility is intentionally manual and read-only: no DB access, no network calls, no EDXEIX calls, no AADE calls, no queue jobs, and no email body storage.
```
