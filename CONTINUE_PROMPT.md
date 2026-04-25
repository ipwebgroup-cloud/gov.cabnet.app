You are continuing development of the gov.cabnet.app Bolt → EDXEIX integration project.

Project context:
- Domain: https://gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Public webroot: /home/cabnet/public_html/gov.cabnet.app
- Private folders: /home/cabnet/gov.cabnet.app_app, /home/cabnet/gov.cabnet.app_config, /home/cabnet/gov.cabnet.app_sql
- Treat latest uploaded files as source of truth.
- Do not ask for API keys, DB passwords, cookies, CSRF tokens, or other secrets. Use placeholders and assume secrets already exist in server config files.

Current integration goal:
Build and harden a safe Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue/readiness workflow.

Important safety rule:
No live EDXEIX submission has been performed or approved. Do not create automatic live submission behavior. Keep all work read-only, dry-run, preflight, queue, local-only, guarded admin mapping updates, or audit-only unless explicitly asked for a live submit patch after a real eligible future Bolt trip exists.

Validated status:
1. Bolt API connection works.
2. Bolt reference sync works.
3. Bolt order sync works.
4. Dry-run local future booking harness was validated.
5. LAB cleanup was validated.
6. Ops access guard is installed and verified for allowed IP access.
7. Readiness reached READY_FOR_REAL_BOLT_FUTURE_TEST after cleanup.
8. Mapping dashboard exists at /ops/mappings.php.
9. JSON output from mappings is sanitized and excludes raw_payload_json.
10. Mapping editor patch adds POST-only EDXEIX ID updates for mapping rows with audit logging.

Next live test blocker:
A real Bolt ride must be scheduled at least 40–60 minutes in the future before a true live-safe EDXEIX candidate can exist. Live submission is still disabled.

When continuing:
1. Inspect uploaded files before patching.
2. Prefer the smallest safe patch.
3. Include exact cPanel upload paths.
4. Include SQL when needed.
5. Include suggested Git commit title and description.
6. Keep live submission disabled unless explicitly requested.
