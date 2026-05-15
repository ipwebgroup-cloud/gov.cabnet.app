You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Continue from this checkpoint:

- v3.1.0 added the read-only V3 Real-Mail Queue Health audit.
- v3.1.1 adds navigation links for that audit in `/ops/_shell.php`.
- Real-Mail Queue Health route: `/ops/pre-ride-email-v3-real-mail-queue-health.php`.

Safety posture:

- Production Pre-Ride Tool untouched.
- V0 untouched.
- Live EDXEIX submission disabled.
- V3 live gate closed.
- No route moves/deletes/redirects.
- No SQL changes.
- No Bolt, EDXEIX, or AADE calls.
- No DB writes or queue mutations.

Next safest step:

Verify v3.1.1 on live, then continue with read-only observation of V3 real-mail intake and queue health. Do not enable live submission.
