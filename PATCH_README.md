# Patch README — GOV Ops Admin Shell v2.0

## Summary

Adds EDXEIX-style read-only companion pages for administration visibility:
- Admin Control
- Readiness Control
- Mapping Review
- Jobs Review

## Files

- `public_html/gov.cabnet.app/assets/css/gov-ops-edxeix.css`
- `public_html/gov.cabnet.app/ops/admin-control.php`
- `public_html/gov.cabnet.app/ops/readiness-control.php`
- `public_html/gov.cabnet.app/ops/mapping-control.php`
- `public_html/gov.cabnet.app/ops/jobs-control.php`
- `docs/GOV_OPS_ADMIN_SHELL_V2_0.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## SQL

None.

## Safety

Presentation/read-only companion pages only. No live submission, no EDXEIX HTTP call, no Bolt call added, no queue staging, and no mapping updates.
