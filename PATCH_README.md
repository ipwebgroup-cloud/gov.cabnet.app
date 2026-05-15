# Patch README — v3.1.7 Shell Note Cosmetic Cleanup

## What changed

This patch updates only `/ops/_shell.php` text metadata and the sidebar note spelling/spacing.

## Files included

```text
public_html/gov.cabnet.app/ops/_shell.php
docs/V3_SHELL_NOTE_COSMETIC_CLEANUP_20260515.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload path

Upload:

```text
public_html/gov.cabnet.app/ops/_shell.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Optional docs mirror:

```text
/home/cabnet/docs/V3_SHELL_NOTE_COSMETIC_CLEANUP_20260515.md
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

grep -n "v3.1.7\|legacy stats source audit navigation\|added in v3.1.6" \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Expected result

- PHP syntax clean.
- Unauthenticated route returns 302 to `/ops/login.php`.
- `v3.1.7` marker present.
- Corrected shell note text present.

## Safety

No live behavior changes. No database writes. No queue mutations. Live EDXEIX submission remains disabled.
