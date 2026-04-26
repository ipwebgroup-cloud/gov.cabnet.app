# Patch: Bolt Test Session Control v1.5

## What changed

Adds a low-risk workflow launcher:

```text
public_html/gov.cabnet.app/ops/test-session.php
```

This gives the operator one page for the real future Bolt test workflow:

- readiness passport
- capture links for accepted / pickup / started / completed
- auto-watch link
- evidence bundle link
- evidence report Markdown link
- preflight JSON link
- JSON status endpoint

## Safety

The new page itself:

- does not call Bolt
- does not call EDXEIX
- does not stage jobs
- does not update mappings
- does not write database rows or files
- does not enable live submission

The capture buttons link to the existing Dev Accelerator dry-run probes.

## Files included

```text
public_html/gov.cabnet.app/ops/test-session.php
docs/BOLT_TEST_SESSION_CONTROL.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/test-session.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/test-session.php
```

Optional docs/repo files:

```text
docs/BOLT_TEST_SESSION_CONTROL.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/test-session.php
```

URLs:

```text
https://gov.cabnet.app/ops/test-session.php
https://gov.cabnet.app/ops/test-session.php?format=json
```

## Expected result

- Page loads cleanly.
- JSON endpoint returns valid JSON.
- It shows current readiness state.
- It confirms live submit is disabled.
- It provides one safe workflow to capture and export evidence.
