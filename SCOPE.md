# Scope

## Goal

Build and harden a safe Bolt / pre-ride email → normalized local readiness → EDXEIX preflight/queue/submit diagnostic workflow.

## In scope now

- Sync Bolt reference/order data.
- Keep historical/terminal/cancelled/receipt-only/test-like rows blocked.
- Require a +30 minute future guard.
- Diagnose EDXEIX submit redirects without enabling unattended submit.
- Parse pre-ride email into a separate future candidate preview.
- Store sanitized pre-ride candidate metadata only when explicitly requested.
- Provide opt-in redacted source diagnostics for Maildir parser troubleshooting.

## Out of scope until explicit approval

- Unattended EDXEIX live submission.
- Cron-enabled live submission workers.
- Automatic submission of historical, terminal, cancelled, receipt-only, invalid, or past trips.
- Committing production credentials, cookies, API keys, raw email bodies, SQL dumps, or runtime sessions.
