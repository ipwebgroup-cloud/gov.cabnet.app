# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge v5.3

Current addition: driver receipt email now sends an official PDF receipt attachment instead of relying on HTML as the receipt document.

Safety posture remains:
- live EDXEIX submission gated and blocked unless all explicit live gates pass
- no live cron
- no EDXEIX POST during install
- no submission_jobs or submission_attempts created by this patch

The configured/default attachment path is:
`/home/cabnet/gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf`

For production legitimacy, replace/generate that PDF from the official invoicing platform per real receipt. The HTML email body is only a carrier/summary; the PDF attachment is the receipt.
