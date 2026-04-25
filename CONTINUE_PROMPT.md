You are continuing development of the gov.cabnet.app Bolt → EDXEIX integration project.

Project context:
- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Public webroot: /home/cabnet/public_html/gov.cabnet.app
- Private folders: /home/cabnet/gov.cabnet.app_app, /home/cabnet/gov.cabnet.app_config, /home/cabnet/gov.cabnet.app_sql
- Treat latest uploaded/server files as source of truth.
- Do not ask for API keys, DB passwords, cookies, CSRF tokens, or other secrets.

Current state:
- Bolt API, reference sync, and order sync work.
- Normalized bookings work.
- Preflight payload preview works.
- Local staging and dry-run worker audit work.
- LAB dry-run harness and cleanup were validated.
- Access guard is active.
- Guided ops dashboard/help/future-test/mappings pages exist.
- Mapping editor is guarded and audits EDXEIX ID changes.
- Mapping JSON is sanitized.
- Live-submit gate scaffold exists at /ops/live-submit.php, but live HTTP execution is intentionally blocked.

Current known mappings:
- Filippos Giannakopoulos → EDXEIX driver 17585
- EMX6874 → EDXEIX vehicle 13799
- EHA2545 → EDXEIX vehicle 5949
- Georgios Zachariou remains unmapped for now.

Live-submit status:
- Live EDXEIX submission is disabled.
- /ops/live-submit.php is a safety gate and review panel only.
- The preparatory patch still blocks actual EDXEIX HTTP transport.
- The real config file /home/cabnet/gov.cabnet.app_config/live_submit.php is server-only and ignored by Git.

Before actual live EDXEIX submission can be implemented/executed:
1. Filippos must be available.
2. Create a real Bolt ride 40–60 minutes in the future.
3. Use Filippos with EMX6874 or EHA2545.
4. Run Bolt sync.
5. Verify /ops/future-test.php shows a real future candidate.
6. Verify /bolt_edxeix_preflight.php?limit=30 has a valid payload.
7. Verify /ops/live-submit.php shows the candidate and all technical checks.
8. Confirm exact EDXEIX submit URL/session behavior.
9. Andreas must explicitly approve the final live HTTP execution patch.

Safety rules:
- Never submit LAB/test rows live.
- Never submit terminal/cancelled/finished/expired/past rows.
- Never submit unmapped driver/vehicle rows.
- Never expose credentials, cookies, CSRF tokens, API keys, DB passwords, or session files.
- Preserve cPanel/manual deployment workflow.
- No frameworks, Composer, Node, or heavy dependencies unless Andreas explicitly approves.

When creating patch zips:
- No wrapper folder.
- Zip root must mirror repository/live structure directly.
- Include changed/added files only unless asked for a full archive.
