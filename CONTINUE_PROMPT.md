You are Sophion continuing the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- The app is production-prep ready but not live-submit enabled.
- `/ops/edxeix-session.php` has a guarded server-side save form and fast paste auto-extract helper for EDXEIX request headers/form HTML.
- EDXEIX submit URL and cookie/CSRF session prerequisites were saved and verified server-side.
- `/ops/live-submit.php` correctly shows EDXEIX URL configured and EDXEIX session ready, but live HTTP execution remains blocked.
- A private Firefox extension exists under `tools/firefox-edxeix-session-capture/`.
- Current extension version is `0.1.2`: fixed submit URL, no confirmation phrase, explicit verification buttons that open `/ops/edxeix-session.php` and `/ops/live-submit.php`.
- There are currently no real future Bolt candidates.
- Final live HTTP transport is intentionally not implemented/enabled yet.

Next safest step if Andreas says “continue”:
1. If no real future Bolt ride exists, avoid live transport work unless Andreas explicitly asks for final transport prep.
2. Prefer readiness/UX/documentation or dry-run-only production checklist work.
3. For live submission, require a real future Bolt candidate with Filippos + mapped vehicle and explicit approval.

Critical safety:
- Never request or expose cookies, CSRF tokens, API keys, or DB credentials.
- Never commit server-only config/session files.
- Never enable or implement live EDXEIX submission unless explicitly approved.
