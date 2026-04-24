# Security Policy / Operational Guardrails

This repo intentionally excludes production secrets and runtime data.

## Never commit

- Database names/users/passwords that identify production access
- Bolt client IDs/secrets
- Internal API keys
- EDXEIX cookies or CSRF tokens
- Raw Bolt payloads or SQL data dumps
- Logs and runtime artifacts

## Before public exposure

- Restrict `/ops` by cPanel directory password, server auth, VPN, or IP allowlist.
- Protect JSON endpoints with an internal key or server-level access control.
- Remove temporary public diagnostic scripts.
- Rotate any credentials that were ever included in exported ZIPs.

## Live submission guard

Live EDXEIX POST behavior must be a separately reviewed patch with explicit owner approval.
