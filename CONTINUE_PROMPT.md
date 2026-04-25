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
No live EDXEIX submission has been performed or approved. Do not create automatic live submission behavior. Keep all work read-only, dry-run, preflight, queue, local-only, or guarded admin-only unless explicitly asked for a live-submit patch after a real eligible future Bolt trip exists.

Validated status:
1. Bolt API connection works.
2. Bolt reference sync works.
3. Bolt order sync works.
4. Ops access guard is installed and verified.
5. Dry-run future booking harness was validated end-to-end.
6. LAB cleanup was validated and system returned to clean state.
7. Mapping dashboard is installed; JSON is sanitized.
8. Mapping editor is guarded and audit-logged.
9. Known EDXEIX driver references are visible in mappings:
   - 1658 — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
   - 17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
   - 6026 — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
10. Georgios Zachariou remains unmapped until his exact EDXEIX driver ID is independently confirmed.
11. New real future-test checklist exists at /ops/future-test.php.
12. Live EDXEIX submission is still disabled and not authorized.

Next live test blocker:
A real Bolt ride must be scheduled at least 40–60 minutes in the future using a mapped driver and mapped vehicle before true live-path preflight can be tested.

When continuing:
1. Inspect uploaded files before patching.
2. Prefer the smallest safe patch.
3. Include exact cPanel upload paths.
4. Include SQL only when required.
5. Include suggested Git commit title and description.
6. Keep live submission disabled unless explicitly requested.
7. When creating zips, do not wrap files in a package folder; the zip root must mirror the live/repository structure directly.
