# EDXEIX Session Page — Extension-Only Operator UI

This patch removes the manual Cookie/CSRF input workflow from `/ops/edxeix-session.php`.

## Why

The Firefox extension now captures and saves the EDXEIX session prerequisites directly through `/ops/edxeix-session-capture.php`, so the visible manual input fields on the session readiness page were confusing operators.

## New operator workflow

1. Log in to EDXEIX.
2. Open `https://edxeix.yme.gov.gr/dashboard/lease-agreement/create`.
3. Click the CABnet EDXEIX Capture Firefox extension.
4. Click **Capture from EDXEIX tab**.
5. Click **Save to gov.cabnet.app**.
6. Verify `/ops/edxeix-session.php` shows:
   - Session cookie/CSRF ready: yes
   - Submit URL configured: yes
   - Placeholder values: not detected

## Safety

- `/ops/edxeix-session.php` is now diagnostic/read-only for normal operators.
- The page does not display or pre-fill Cookie/CSRF values.
- The extension capture endpoint still validates the EDXEIX host, rejects placeholders, creates backups, and forces `live_submit_enabled` and `http_submit_enabled` to remain disabled.
- Live EDXEIX HTTP transport remains blocked.
