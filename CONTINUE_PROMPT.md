You are Sophion continuing the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- The app is production-prep ready but not live-submit enabled.
- `/ops/edxeix-session-capture.php` receives EDXEIX session prerequisites from the private Firefox extension.
- The Firefox extension captures the EDXEIX create-form CSRF token and cookies, then saves them server-side.
- `/ops/edxeix-session.php` is now a diagnostic/read-only operator page; manual Cookie/CSRF input fields were removed to avoid confusion.
- EDXEIX submit URL and cookie/CSRF session prerequisites were saved and verified server-side.
- `/ops/live-submit.php` correctly shows EDXEIX URL configured and EDXEIX session ready, but live HTTP execution remains blocked.
- There are currently no real future Bolt candidates.
- Final live HTTP transport is intentionally not implemented/enabled yet.

Next safest step if Andreas says “continue”:
1. If no real future Bolt ride exists, avoid live transport work unless Andreas explicitly asks for final transport prep.
2. Prefer readiness/UX/documentation or a dry-run-only production checklist.
3. For live submission, require a real future Bolt candidate with Filippos + mapped vehicle and explicit approval.

Critical safety:
- Never request or expose cookies, CSRF tokens, API keys, or DB credentials.
- Never commit server-only config/session files.
- Never enable or implement live EDXEIX submission unless explicitly approved.
