# Bolt Ops UI Polish v1.7

## Purpose

Give the main Bolt → EDXEIX operations workflow a more formal EDXEIX-style administrative appearance while leaving all operational logic untouched.

## Scope

This first polish pass intentionally touches only the two main operator pages:

- `/ops/test-session.php`
- `/ops/preflight-review.php`

It adds a shared stylesheet:

- `/assets/css/gov-ops-edxeix.css`

## Design goals

- More official administrative feel.
- Dark navy header with gold accent.
- More compact status badges.
- Stronger green/orange/red safety language.
- Cleaner card and table styling.
- Same page routes and same operator flow.

## Non-goals

This patch does not introduce Bootstrap, JavaScript frameworks, Composer, Node, or shared layout refactoring. It does not alter preflight logic, queue logic, mapping logic, or live submit behavior.

## Safety posture

Live EDXEIX submission remains disabled. The affected pages remain read-only workflow/review pages. Capture links still point to the existing Dev Accelerator dry-run visibility probes.
