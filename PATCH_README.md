# Patch: Live Submit Global Session Status

## Files included

- `public_html/gov.cabnet.app/ops/live-submit.php`
- `docs/LIVE_SUBMIT_GLOBAL_SESSION_STATUS.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

```text
public_html/gov.cabnet.app/ops/live-submit.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/live-submit.php
```

## SQL

No SQL required.

## Verification

Open:

```text
https://gov.cabnet.app/ops/live-submit.php
https://gov.cabnet.app/ops/live-submit.php?format=json
```

Expected after EDXEIX session/save URL has been prepared:

```text
EDXEIX SESSION READY badge visible
EDXEIX session ready requirement: pass
EDXEIX submit URL configured: pass
Real future Bolt candidate exists: waiting
Live HTTP execution: no
```

Live submission remains blocked.
