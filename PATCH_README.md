# Patch README — V3 Real-Mail Observation Milestone Docs

## Package

`gov_v3_real_mail_observation_milestone_docs_20260515.zip`

## Type

Documentation-only milestone package.

## Files included

```text
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
docs/V3_REAL_MAIL_OBSERVATION_MILESTONE_20260515.md
```

## Upload / repo paths

Extract into the repository root:

```text
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
docs/V3_REAL_MAIL_OBSERVATION_MILESTONE_20260515.md
```

Optional live docs mirror:

```text
/home/cabnet/docs/V3_REAL_MAIL_OBSERVATION_MILESTONE_20260515.md
```

## SQL

None.

## Safety

Documentation only. No PHP route behavior changes. No DB writes. No queue mutations. No live-submit changes.

## Verification

```bash
find . -maxdepth 2 -type f | grep -E 'HANDOFF.md|CONTINUE_PROMPT.md|PATCH_README.md|V3_REAL_MAIL_OBSERVATION_MILESTONE_20260515.md'
```

Expected:

```text
./HANDOFF.md
./CONTINUE_PROMPT.md
./PATCH_README.md
./docs/V3_REAL_MAIL_OBSERVATION_MILESTONE_20260515.md
```
