# CONTINUE PROMPT — gov.cabnet.app Bolt → EDXEIX Bridge

You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

## Current state

The live Handoff Center now has three package modes:

1. Private Operational ZIP — includes database export; private only; never commit.
2. Git-Safe Continuity ZIP — DB-free, runtime/session/proof-artifact scrubbed; intended for local repo continuity review.
3. Git-Safe + DB Audit ZIP — includes `DATABASE_EXPORT.sql` for private live-site/database audit, while preserving runtime/session/proof-artifact scrubbing; never commit.

The Handoff Center version for this change is:

```text
v3.0.78-v3-git-safe-db-audit-option
```

## Safety rules

- Do not enable live EDXEIX submission unless Andreas explicitly asks.
- Keep V3 closed-gate behavior.
- Do not commit database exports, runtime sessions, proof artifacts, mailboxes, logs, or real config values.
- No frameworks, Composer, Node, or heavy dependencies.
- Preserve plain PHP/mysqli/cPanel/manual upload workflow.

## Next safest audit step

Generate the new Git-Safe + DB Audit ZIP and inspect it for:

- `DATABASE_EXPORT.sql` present.
- `GIT_SAFE_WITH_DB_AUDIT_NOTICE.md` present.
- No `storage/runtime`, `edxeix_session`, `cookie_header`, `csrf`, `xsrf`, `laravel_session`, `storage/artifacts`, `.bak`, or `.pre_` entries.

Then use that private package for live-site/database audit only.
