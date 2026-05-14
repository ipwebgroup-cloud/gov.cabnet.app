# Patch README — v3.0.69 V3 Adapter Payload Consistency Harness

## What changed

Adds a V3-only read-only adapter payload consistency harness and Ops page.

The harness compares:

- DB-built EDXEIX field package,
- latest package export `edxeix_fields.json`,
- future adapter skeleton payload hash.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php
docs/V3_ADAPTER_PAYLOAD_CONSISTENCY_HARNESS.md
docs/V3_AUTOMATION_NEXT_STEPS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php
```

Keep docs in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --json"
```

Optional row-specific check:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --queue-id=427 --json"
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-payload-consistency.php
```

## Expected result

```text
Simulation safe: yes
adapter live_capable=no
adapter submitted=no
No EDXEIX call
No AADE call
No DB writes
No queue status changes
V0 untouched
```

If the selected row does not have a package-export artifact, the harness will report a consistency block. That is safe.

## Commit title

```text
Add V3 adapter payload consistency harness
```

## Commit description

```text
Adds a V3-only read-only adapter payload consistency harness and Ops page.

The harness compares the DB-built EDXEIX field package, latest package export artifact, and future adapter skeleton payload hash for a selected V3 queue row. It confirms the adapter remains non-live-capable and submitted=false.

No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, DB writes, queue status changes, production submission table writes, cron schedules, or SQL schema are changed.
```
