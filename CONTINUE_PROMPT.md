Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state after v3.7:
- Bolt Ride details emails are forwarded from Gmail to `bolt-bridge@gov.cabnet.app`.
- Maildir scanner cron runs every 2 minutes and imports into `bolt_mail_intake`.
- Existing historical test emails imported successfully and are blocked as `blocked_past`.
- v3.7 adds `/ops/mail-preflight.php?key=INTERNAL_KEY`.
- That page previews only `future_candidate` mail rows and can manually create a local `normalized_bookings` row for preflight review.
- It does not create submission jobs.
- It does not submit to EDXEIX.
- Live EDXEIX submission remains disabled.

Next safest step:
Wait for a real future Bolt Ride details email. Confirm it imports as `future_candidate`, open `/ops/mail-preflight.php`, verify mapping status and payload preview, create a local preflight booking only if all checks pass, then review `/bolt_edxeix_preflight.php?limit=30`.

Do not enable live EDXEIX submission unless Andreas explicitly asks after a real eligible future Bolt trip passes all checks.
