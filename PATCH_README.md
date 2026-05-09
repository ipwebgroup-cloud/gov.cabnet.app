# gov.cabnet.app v6.5.3 Documentation Sync

## What changed

Documentation-only sync so repository continuity matches the confirmed v6.5.2 production-safe code state.

No PHP code changes.
No SQL changes.
No production behavior changes.
No EDXEIX live activation.
No AADE behavior changes.

## Files included

```text
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

This package is intended primarily for the local GitHub Desktop repo.

Extract into the local repo root:

```text
<local-repo-root>/HANDOFF.md
<local-repo-root>/CONTINUE_PROMPT.md
<local-repo-root>/PATCH_README.md
```

Server upload is optional for these docs. If uploaded to server for reference:

```text
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/PATCH_README.md
```

## SQL

None.

## Verification

After extracting locally, GitHub Desktop should show only documentation changes.

Expected files changed:

```text
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Expected result

The repo handoff files reflect:

- v6.5.2 code commit is complete.
- AADE issuing is pickup timestamp worker-only.
- Mail/manual AADE issuing paths are blocked/no-op.
- EDXEIX live submission remains disabled.
- EDXEIX queues must remain zero unless explicitly approved.
- Bolt pickup timestamp timing is not proven before ride finish.

## Git commit title

```text
Sync handoff docs with AADE pickup-only v6.5.2 state
```

## Git commit description

```text
Updates HANDOFF.md and CONTINUE_PROMPT.md so repository continuity matches the confirmed v6.5.2 production posture.

Documents:
- AADE issuing is active only through the Bolt API pickup timestamp worker.
- Pre-ride mail/manual/auto AADE issue paths are blocked or no-op.
- Duplicate AADE receipt incident and the central duplicate/source guards.
- EDXEIX remains disabled with queues expected at zero.
- Bolt pickup timestamp timing is not proven before ride finish.
- Future deliverables must be zip packages extracted locally before manual upload/commit.

No PHP code changes, no SQL changes, and no production behavior changes.
```
