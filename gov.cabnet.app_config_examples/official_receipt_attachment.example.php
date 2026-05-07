<?php
// Example server-only config keys for /home/cabnet/gov.cabnet.app_config/config.php
// Add these inside: 'mail' => ['driver_notifications' => [...]]
// Do not commit real secrets. This contains no secrets.

'receipt_copy_enabled' => true,
'receipt_pdf_attachment_enabled' => true,
'receipt_pdf_attachment_required' => true,
'receipt_pdf_attachment_path' => '/home/cabnet/gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf',
'receipt_pdf_attachment_filename' => 'lux-limo-receipt.pdf',
