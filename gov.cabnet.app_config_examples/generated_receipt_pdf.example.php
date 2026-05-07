<?php
// Example server-only config additions for dynamic bridge-generated receipt PDFs.
// Add inside config.php under ['mail']['driver_notifications'].
// Do not commit real secrets to Git.
return [
    'receipt_copy_enabled' => true,
    'receipt_pdf_mode' => 'generated',
    'receipt_pdf_attachment_required' => true,
    'receipt_vat_rate_percent' => 13,
    'generated_receipt_pdf_filename_prefix' => 'lux-limo-transfer-receipt',
    'receipt_logo_path' => '/home/cabnet/public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg',
    'receipt_stamp_path' => '/home/cabnet/public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg',
];
