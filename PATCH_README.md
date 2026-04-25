# Patch: Live Submit Candidate Wording Fix

## Upload path

Upload:

```text
public_html/gov.cabnet.app/ops/live-submit.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/live-submit.php
```

Commit docs/root files to GitHub:

```text
docs/LIVE_SUBMIT_CANDIDATE_WORDING_FIX.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

No SQL required.

## Verify

Open:

```text
https://gov.cabnet.app/ops/live-submit.php
https://gov.cabnet.app/ops/live-submit.php?format=json
```

Expected while no real future Bolt trip exists:

```text
Analyzed recent rows: may be 4
Real future candidates: 0
Live-eligible rows: 0
Selected booking: none
selected_is_real_future_candidate: false
No EDXEIX HTTP request performed
```

## Safety

This patch does not enable live EDXEIX submission. Live HTTP transport remains blocked.
