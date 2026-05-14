# Patch README — v3.0.73 V3 Proof Ledger

## What changed

Adds a read-only V3 proof ledger so Andreas can review the current pre-live proof evidence without searching artifact folders manually.

This patch adds:

- A CLI ledger that indexes proof bundle artifacts and package exports.
- An Ops page that lists the latest proof bundles and local EDXEIX field package exports.
- Updated handoff and continuation documentation.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php
docs/V3_AUTOMATION_PRE_LIVE_STATUS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

Upload these files exactly as follows:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php

docs/V3_AUTOMATION_PRE_LIVE_STATUS.md
→ /home/cabnet/public_html/gov.cabnet.app/docs/V3_AUTOMATION_PRE_LIVE_STATUS.md only if docs are served from public docs
→ otherwise keep in Git repo docs/V3_AUTOMATION_PRE_LIVE_STATUS.md

HANDOFF.md
→ repo root / HANDOFF.md

CONTINUE_PROMPT.md
→ repo root / CONTINUE_PROMPT.md

PATCH_README.md
→ repo root / PATCH_README.md
```

## SQL

No SQL changes.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php --json"
```

Ops URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php
```

Expected CLI result:

```text
V3 proof ledger v3.0.73-v3-proof-ledger
Mode: read_only_proof_ledger
Safety: No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.
OK: yes
```

Expected Ops result:

- Page loads after Ops login.
- Shows latest proof bundles.
- Shows latest local EDXEIX field package exports.
- Shows no-call/no-write safety badges.
- Does not execute commands from the browser.

## Git commit title

```text
Add V3 proof ledger for pre-live evidence review
```

## Git commit description

```text
Adds a read-only V3 proof ledger CLI and Ops page for reviewing pre-live proof bundle artifacts and local EDXEIX field package exports.

The ledger indexes existing artifacts under the private app storage directory, summarizes bundle safety flags, payload consistency, adapter hash matching, no-call evidence, and local package export metadata.

The Ops page does not execute commands and does not write files. The CLI is read-only and performs only optional SELECT-style queue/approval counts.

Live EDXEIX submission remains disabled. No Bolt call, no EDXEIX call, no AADE call, no DB writes, no queue status changes, no production submission table writes, and V0 remains untouched.
```
