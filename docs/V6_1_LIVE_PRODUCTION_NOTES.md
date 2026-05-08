# v6.1 Live Production Notes

- Bolt mail intake is active.
- Driver pre-ride emails are active.
- AADE/myDATA receipt issuing is active.
- Late Bolt mail can be recovered for AADE receipt purposes.
- AADE receipt recovery can proceed without EDXEIX vehicle mapping when the Bolt vehicle plate is present.
- EDXEIX live submit remains separate and must not run for past trips.
- No EDXEIX cron is active.

Validated:
- Intake 21 / Booking 17: AADE issued, PDF generated, receipt email sent.
- Intake 22 / Booking 18: AADE issued, PDF generated, receipt email sent to driver, office copy sent to mykonoscab@gmail.com.
- submission_jobs = 0
- submission_attempts = 0

Server-only config is excluded from this commit.
