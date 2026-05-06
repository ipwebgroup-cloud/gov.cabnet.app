# Bolt Mail Dry-run Evidence v4.2

This patch adds a protected dry-run evidence layer for `source='bolt_mail'` normalized bookings.

## Safety contract

The dry-run evidence page:

- does not call Bolt;
- does not call EDXEIX;
- does not create `submission_jobs`;
- does not create `submission_attempts`;
- does not POST live;
- records only a local payload/mapping/safety snapshot in `bolt_mail_dry_run_evidence`.

## Main URL

`/ops/mail-dry-run-evidence.php?key=YOUR_INTERNAL_API_KEY`

## Workflow

1. Create or receive a valid future Bolt mail intake row.
2. Use Mail Preflight to manually create a local `normalized_bookings` row.
3. Open Dry-run Evidence.
4. Preview the generated EDXEIX payload snapshot.
5. Record dry-run evidence.
6. Confirm no `submission_jobs` were created.

## SQL

Run:

`gov.cabnet.app_sql/2026_05_06_bolt_mail_dry_run_evidence.sql`

This creates `bolt_mail_dry_run_evidence` only.
