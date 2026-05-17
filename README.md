# gov.cabnet.app — Bolt → EDXEIX Integration

Plain PHP + mysqli/MariaDB project for a safe Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue/readiness workflow.

## Current safety posture

No unattended live EDXEIX submission is enabled.

Current ASAP automation track:

- EDXEIX submit diagnostic tracing is installed.
- Future guard is 30 minutes.
- Historical/terminal/cancelled/mail receipt-only/test-like rows remain blocked.
- Pre-ride future candidate diagnostics are installed as a separate path.
- v3.2.24 adds opt-in redacted source diagnostics for Maildir parser troubleshooting.

## cPanel layout

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Important commands

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --debug-source=1
```
