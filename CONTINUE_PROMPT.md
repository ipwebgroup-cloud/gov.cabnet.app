You are continuing development of the gov.cabnet.app Bolt → EDXEIX bridge project.

Project:
- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.
- Do not introduce frameworks, Composer, Node, or heavy dependencies.

Source-of-truth order:
1. Latest uploaded files / pasted code / screenshots / live audit output in the current chat.
2. HANDOFF.md and CONTINUE_PROMPT.md.
3. README/SCOPE/DEPLOYMENT/SECURITY/docs.
4. GitHub repo.

Current validated state:
- Ops access guard is active.
- `/ops/index.php` is a safe guided operations dashboard.
- `/ops/help.php` is a novice help page and includes EDXEIX session preparation steps.
- `/ops/readiness.php` and `/ops/future-test.php` are the main readiness checks.
- `/ops/mappings.php` contains the mapping dashboard/editor.
- `/ops/live-submit.php` is a disabled live-submit gate; live HTTP transport is still blocked.
- `/ops/edxeix-session.php` checks EDXEIX session and submit URL readiness without printing secrets.
- `edxeix_live_submission_audit` table exists.
- Live EDXEIX submission is disabled and unauthorized.

Latest patch delivered:
- Placeholder detection for EDXEIX runtime session values.
- Updated `/ops/edxeix-session.php`.
- Updated `gov.cabnet.app_app/lib/edxeix_live_submit_gate.php`.
- If the copied template exists at `/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json`, the system should report placeholder/example values detected and session ready = no.

Real server-only files must remain ignored and uncommitted:
- `/home/cabnet/gov.cabnet.app_config/live_submit.php`
- `/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json`

Known mappings:
- Filippos Giannakopoulos → EDXEIX driver ID 17585.
- EMX6874 → EDXEIX vehicle ID 13799.
- EHA2545 → EDXEIX vehicle ID 5949.
- Georgios Zachariou must remain unmapped for now.

Next safest steps:
1. Verify `/ops/edxeix-session.php` after upload.
2. Confirm placeholders are no longer counted as a ready session.
3. When Andreas is ready, guide him to enter real EDXEIX cookie/CSRF values only into the server-side `edxeix_session.json`; never ask him to paste secrets into chat.
4. Keep `live_submit_enabled` and `http_submit_enabled` false.
5. Do not build or enable final HTTP live transport until a real future Bolt candidate exists and Andreas explicitly approves.

Critical safety rules:
- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Never submit LAB/test/historical/cancelled/terminal/past Bolt orders to EDXEIX.
- Never expose credentials, cookies, CSRF tokens, DB passwords, session files, or API keys.
- Config examples may be committed; real config/session files must stay server-only and ignored.
- Keep cPanel paths and file names stable.

When creating deployment zips:
- Do not wrap files in an extra package folder.
- Zip root must mirror live/repo structure directly.
- Include only changed/added files unless Andreas asks for a full archive.
