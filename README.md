# gov.cabnet.app — Bolt → EDXEIX Integration

Plain PHP + mysqli/MariaDB project for a safe Bolt Fleet API / pre-ride email → normalized local readiness → EDXEIX diagnostic/preflight workflow.

## Current safety posture

No unattended live EDXEIX submission is enabled. Current work is focused on diagnostic readiness for future pre-ride emails.

## Current ASAP track

- Server-side future guard: 30 minutes.
- Existing Bolt API rows remain blocked when historical, terminal, cancelled, lab/test, or not future.
- `bolt_mail` receipt-only rows remain blocked from EDXEIX.
- `bolt_pre_ride_email` is a separate diagnostic path.
- v3.2.26 fixes diagnostics-only fallback parsing for HTML label rows.

## cPanel layout

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```
