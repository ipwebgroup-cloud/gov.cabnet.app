# Ops UI Shell Phase 57 — Mobile Submit Trial Run

Adds `/ops/mobile-submit-trial-run.php`, a real-email dry-run evaluator for the future mobile/server-side EDXEIX submit workflow.

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not write workflow data.
- Does not stage jobs.
- Does not enable live EDXEIX submission.

## Purpose

The page allows an admin/operator to load the latest server email or paste a Bolt pre-ride email and run it through the dry-run chain:

1. Bolt email parser.
2. EDXEIX mapping resolver.
3. Lessor-specific starting point evidence.
4. Latest sanitized submit capture.
5. EDXEIX preflight gate.
6. Disabled connector request preview.
7. Payload validator.

It returns a single final trial result:

- `DRY-RUN READY / LIVE BLOCKED`, or
- `NO-GO / REVIEW REQUIRED`.

Live submit remains blocked by design.
