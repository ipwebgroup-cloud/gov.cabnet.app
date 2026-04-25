You are continuing development of the gov.cabnet.app Bolt → EDXEIX integration project.

Project context:
- Domain: https://gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Public webroot: /home/cabnet/public_html/gov.cabnet.app
- Private folders: /home/cabnet/gov.cabnet.app_app, /home/cabnet/gov.cabnet.app_config, /home/cabnet/gov.cabnet.app_sql
- Treat latest uploaded files as source of truth.
- Do not ask for API keys, DB passwords, cookies, CSRF tokens, or other secrets.

Current integration goal:
Maintain and safely develop the Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue/readiness workflow.

Important safety rule:
No live EDXEIX submission has been performed or approved. Do not create automatic live submission behavior. Keep all work read-only, dry-run, preflight, queue visibility, or local-only unless explicitly asked for a live submit patch after a real eligible future Bolt trip exists.

Validated status:
1. Bolt API connection works.
2. Bolt reference sync works.
3. Bolt order sync works.
4. Dry-run future booking harness was validated.
5. LAB cleanup was validated and returned the system to a clean state.
6. Ops access guard is active and protects ops/diagnostic endpoints.
7. Mapping dashboard/editor exists; JSON output is sanitized.
8. Known EDXEIX driver references are visible as reference-only notes.
9. Real future-test checklist exists.
10. Legacy /ops/index.php has been replaced by a safe read-only landing page.
11. Live EDXEIX submission remains disabled.

Current operational gate:
The project is ready for a real Bolt future-ride preflight test when Andreas can create/schedule a real Bolt ride at least 40–60 minutes in the future using a mapped driver and mapped vehicle.

When continuing:
- Inspect uploaded files before patching.
- Prefer the smallest safe patch.
- Include exact cPanel upload paths.
- Include suggested Git commit title and description.
- Keep live submission disabled unless explicitly requested.
- Deployment patch zips must have root structure matching live folders directly, with no wrapper folder.
