Continue the gov.cabnet.app Bolt → EDXEIX bridge project from v5.4.

The bridge is running safe mail intake and driver notifications. Driver notification recipient resolution uses Bolt driver identity, not vehicle plate. v5.4 adds dynamic bridge-generated receipt PDFs attached to the second driver receipt email.

Maintain safety:
- No live EDXEIX submission unless explicitly approved.
- No automatic live-submit cron.
- Historical/cancelled/past/terminal rows must never submit.
- Generated receipt PDFs are bridge-generated/pro-forma unless official invoicing integration is added.

Next safest step:
- Test the dynamic receipt PDF email to mykonoscab@gmail.com.
- Verify the PDF attachment contains ride-specific details, VAT/TAX 13%, company branding/stamp, and a bridge verification QR/hash block.
- Verify submission_jobs=0 and submission_attempts=0.
