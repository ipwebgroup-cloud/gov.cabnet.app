# Patch: EDXEIX Session Readiness Helper

## Files included

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
docs/EDXEIX_SESSION_READINESS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session.php
```

```text
docs/EDXEIX_SESSION_READINESS.md
→ repository docs/EDXEIX_SESSION_READINESS.md
```

## SQL

No SQL required.

## Verify

Open:

```text
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
```

Expected before server-side EDXEIX session setup:

```text
Session cookie/CSRF ready: no
Submit URL configured: no
No secrets displayed
No EDXEIX call performed
```

## Safety

This patch is read-only. It does not call Bolt, call EDXEIX, write to the database, create jobs, or enable live submission.
