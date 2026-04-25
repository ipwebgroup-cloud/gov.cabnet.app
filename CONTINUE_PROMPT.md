You are continuing development of the gov.cabnet.app Bolt → EDXEIX bridge project.

Project context:
- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Public webroot: /home/cabnet/public_html/gov.cabnet.app
- Private app folder: /home/cabnet/gov.cabnet.app_app
- Private config folder: /home/cabnet/gov.cabnet.app_config
- SQL folder: /home/cabnet/gov.cabnet.app_sql

Source-of-truth order:
1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. HANDOFF.md and CONTINUE_PROMPT.md.
3. README.md, SCOPE.md, DEPLOYMENT.md, SECURITY.md, docs/, and PROJECT_FILE_MANIFEST.md.
4. GitHub repo.
5. Memory/context only as background.

Critical safety rule:
Live EDXEIX submission is disabled and unauthorized. Do not create automatic or live submission behavior unless Andreas explicitly asks for a separate live-submit patch after a real eligible future Bolt trip has passed preflight.

Current validated baseline:
- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Dry-run future booking harness was validated.
- LAB cleanup was validated.
- Ops access guard is active.
- Legacy /ops/index.php has been replaced with a safe guided operations console.
- /ops/help.php exists as a novice operator help/glossary page.
- /ops/future-test.php exists as a read-only real future Bolt test checklist with a visual progress rail.
- /ops/mappings.php exists as a guarded mapping coverage/editor page.
- Mapping JSON output is sanitized and excludes raw_payload_json.
- Live EDXEIX submission remains disabled.

Expected current state:
- /ops/readiness.php: READY_FOR_REAL_BOLT_FUTURE_TEST
- /ops/future-test.php: READY TO CREATE REAL FUTURE TEST RIDE
- Real future candidates: 0
- LAB/test rows/jobs/attempts: 0
- Local submission jobs: 0
- Live attempts indicated: 0
- Live submission authorization: 0

Known mappings:
- Filippos Giannakopoulos → EDXEIX driver ID 17585
- EMX6874 → EDXEIX vehicle ID 13799
- EHA2545 → EDXEIX vehicle ID 5949

Known reference-only EDXEIX driver IDs:
- 1658 — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
- 17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
- 6026 — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ

Do not map Georgios Zachariou yet. Leave him unmapped until his exact EDXEIX driver ID is independently confirmed.

Remaining blocker:
Andreas cannot create the real future Bolt test ride until Filippos is present/available.

When continuing:
- Prefer read-only, dry-run, preview, audit, and GUI clarity improvements.
- Inspect actual files before patching.
- Keep patch zips rooted directly at live/repo structure; no wrapper directory.
- Include exact upload paths, SQL if any, verification URLs, expected result, git commit title, and git commit description.
- Do not request or expose secrets.
- Do not enable live EDXEIX submission.
