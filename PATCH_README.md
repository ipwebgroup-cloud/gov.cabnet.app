# gov.cabnet.app Patch — Bolt Preflight Review Assistant v1.6

## What changed

Adds:

```text
public_html/gov.cabnet.app/ops/preflight-review.php
docs/BOLT_PREFLIGHT_REVIEW_ASSISTANT.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload path

Upload:

```text
public_html/gov.cabnet.app/ops/preflight-review.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/preflight-review.php
```

Optional documentation files may be committed to the repo.

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/preflight-review.php
```

Open:

```text
https://gov.cabnet.app/ops/preflight-review.php
https://gov.cabnet.app/ops/preflight-review.php?format=json
```

## Expected result

The page loads and reports the current preflight decision in plain language. It should currently show the system is clean but waiting for a real future Bolt candidate.

## Safety

No Bolt call. No EDXEIX call. No job staging. No mapping update. No database write. No live submission.
