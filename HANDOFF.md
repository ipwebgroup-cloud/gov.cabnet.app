# gov.cabnet.app Handoff - v5.4 Dynamic Driver Receipt PDF Generator

Current state after v5.4:

- Bolt mail intake remains active.
- Driver copy email remains active and identity-based.
- Second receipt email now attaches a generated ride-specific PDF.
- PDF includes ride details, 30-minute end-time rule, first-value-only price, VAT/TAX 13%, branding/stamp images when available, and bridge verification QR/hash block.
- Live EDXEIX submit remains guarded and blocked unless separate live gates are explicitly satisfied.
- No live cron exists for EDXEIX submission.

Important boundary:

- The generated PDF is bridge-generated/pro-forma, not official AADE/myDATA unless later connected to an official invoicing provider.

Next recommended actions:

1. Upload v5.4 patch.
2. Add/confirm `receipt_pdf_mode => generated` in server config.
3. Send a receipt test email to mykonoscab@gmail.com.
4. Confirm attachment opens and contains ride details.
5. Confirm submission_jobs and submission_attempts remain 0.
