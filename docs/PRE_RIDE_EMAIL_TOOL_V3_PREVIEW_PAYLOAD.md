# Pre-Ride Email Tool V3 — Diagnostic Preview Payload

## Purpose

This patch updates only the isolated V3 route:

```text
/ops/pre-ride-email-toolv3.php
```

It does not modify the production route:

```text
/ops/pre-ride-email-tool.php
```

## Change

When V3 parses a transfer but blocks helper activation because one or more safety gates fail, the page now shows a diagnostic JSON preview.

The preview is clearly marked:

```text
PREVIEW ONLY — NOT SAVED TO HELPER
```

This allows debugging parsed fields and EDXEIX IDs for past, expired, missing-ID, or otherwise blocked rides without creating an active helper payload.

## Safety

- No DB writes.
- No AADE calls.
- No EDXEIX server-side calls.
- No queue jobs.
- No submission attempts.
- No production file changes.
- Blocked rides still keep the active helper payload empty.

## Expected behavior

For blocked rides:

- Diagnostic preview JSON is visible.
- Active helper payload remains `{}` or empty.
- Save/open helper buttons remain hidden.

For ready future rides:

- Active helper payload works as before.
- Preview envelope also exists internally, but the normal ready controls remain the operator path.
