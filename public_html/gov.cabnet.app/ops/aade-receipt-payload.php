<?php

declare(strict_types=1);

use Bridge\Config;
use Bridge\Database;
use Bridge\Receipts\AadeReceiptPayloadBuilder;

$bootstrap = require '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php';
/** @var Config $config */
$config = $bootstrap['config'];
/** @var Database $db */
$db = $bootstrap['db'];

$bookingId = isset($_GET['booking_id']) ? max(0, (int)$_GET['booking_id']) : 0;
$showXml = (($_GET['show_xml'] ?? '') === '1');
$latest = latest_bolt_mail_bookings($db);
$latestAttempts = latest_receipt_attempts($db);
$preview = null;
$error = null;
$configGate = build_config_gate($config);

if ($bookingId > 0) {
    try {
        $builder = new AadeReceiptPayloadBuilder($config, $db);
        $built = $builder->buildForBookingId($bookingId);
        $preview = [
            'summary' => $built['summary'],
            'validation' => $built['validation'],
            'config_gate' => $configGate,
            'accountant_review_checklist' => build_accountant_review_checklist($built['summary']),
            'send_invoices_status' => !empty($configGate['allow_send_invoices'])
                ? 'CONFIG_ENABLED_STILL_REQUIRES_CONFIRM_PHRASE'
                : 'DISABLED_IN_CONFIG_PREVIEW_ONLY',
            'xml_sha256' => $built['xml_sha256'],
            'xml_bytes' => $built['xml_bytes'],
            'xml_included' => $showXml,
        ];
        if ($showXml) {
            $preview['xml'] = $built['xml'];
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$gateChecks = [
    [
        'label' => 'Receipt mode is AADE/myDATA',
        'ok' => $configGate['receipts_mode'] === 'aade_mydata',
        'detail' => 'receipts.mode must be aade_mydata.',
    ],
    [
        'label' => 'AADE integration is enabled',
        'ok' => $configGate['aade_enabled'] === true,
        'detail' => 'receipts.aade_mydata.enabled should be true only after connectivity is verified.',
    ],
    [
        'label' => 'Driver receipt email remains disabled',
        'ok' => $configGate['driver_receipt_copy_enabled'] === false,
        'detail' => 'Do not email receipts until AADE issuance succeeds and official PDF flow exists.',
    ],
    [
        'label' => 'Driver receipt mode is AADE-only',
        'ok' => $configGate['driver_receipt_pdf_mode'] === 'aade_mydata',
        'detail' => 'Generated/static PDF fallback must remain off.',
    ],
    [
        'label' => 'SendInvoices is disabled by config',
        'ok' => $configGate['allow_send_invoices'] === false,
        'manual' => true,
        'detail' => 'Enable only for the first controlled SendInvoices test after accountant approval.',
    ],
];

$verdict = 'AADE_PAYLOAD_SELECT_BOOKING';
if ($error) {
    $verdict = 'AADE_PAYLOAD_PREVIEW_ERROR';
} elseif ($preview) {
    $verdict = !empty($configGate['allow_send_invoices'])
        ? 'AADE_PAYLOAD_PREVIEW_READY_SEND_CONFIG_ENABLED'
        : 'AADE_PAYLOAD_PREVIEW_READY_SEND_DISABLED';
}

$out = [
    'ok' => $error === null,
    'script' => 'ops/aade-receipt-payload.php',
    'generated_at' => date('c'),
    'verdict' => $verdict,
    'safety_contract' => [
        'read_only' => true,
        'displays_secrets' => false,
        'writes_files' => false,
        'sends_invoices' => false,
        'sends_driver_email' => false,
        'calls_aade' => false,
        'calls_edxeix' => false,
        'creates_submission_jobs' => false,
        'creates_submission_attempts' => false,
    ],
    'config' => $configGate,
    'gate_checks' => $gateChecks,
    'booking_id' => $bookingId ?: null,
    'error' => $error,
    'preview' => $preview,
    'latest_bolt_mail_bookings' => $latest,
    'latest_receipt_attempts' => $latestAttempts,
    'commands' => [
        'preview' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID',
        'preview_with_xml' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID --show-xml',
        'record_prepared' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID --record-prepared --by=Andreas',
        'send_blocked_until_enabled' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID --send --confirm=CONFIRM_PHRASE --by=Andreas',
    ],
];

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AADE Receipt Payload Preview</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f6f8fb}.card{border:0;box-shadow:0 8px 24px rgba(15,23,42,.08)}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}.small-table td,.small-table th{font-size:.88rem}pre{white-space:pre-wrap}.badge-ok{background:#198754}.badge-warn{background:#ffc107;color:#111}.badge-bad{background:#dc3545}</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">AADE Receipt Payload Preview</h1><div class="text-muted">Generated <?= h($out['generated_at']) ?></div></div>
    <span class="badge <?= $error ? 'badge-bad' : ($preview ? 'badge-ok' : 'badge-warn') ?> fs-6"><?= h($out['verdict']) ?></span>
  </div>

  <div class="alert alert-info">Read-only preview. This page does not call AADE, does not send invoices, and does not email receipts.</div>

  <div class="row g-3 mb-3">
    <div class="col-lg-5"><div class="card"><div class="card-body"><h2 class="h5">Config gate</h2><pre class="mono small mb-0"><?= h(json_encode($out['config'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre></div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-body"><h2 class="h5">Gate checks</h2><div class="table-responsive"><table class="table table-sm small-table mb-0"><tbody><?php foreach ($gateChecks as $check): ?><tr><td><span class="badge <?= !empty($check['ok']) ? 'badge-ok' : (!empty($check['manual']) ? 'badge-warn' : 'badge-bad') ?>"><?= !empty($check['ok']) ? 'OK' : (!empty($check['manual']) ? 'MANUAL' : 'BLOCK') ?></span></td><td><?= h((string)$check['label']) ?><br><span class="text-muted"><?= h((string)$check['detail']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
  </div>

  <div class="card mb-3"><div class="card-body"><h2 class="h5">Preview</h2><?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php elseif ($preview): ?><pre class="mono small"><?= h(json_encode($preview, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre><?php else: ?><div class="text-muted">Select a booking using <code>?booking_id=ID</code>.</div><?php endif; ?></div></div>

  <div class="card mb-3"><div class="card-body"><h2 class="h5">Latest bolt_mail bookings</h2><div class="table-responsive"><table class="table table-sm table-striped small-table"><thead><tr><th>ID</th><th>Customer</th><th>Driver</th><th>Plate</th><th>Start</th><th>Price</th><th>Preview</th></tr></thead><tbody>
  <?php foreach ($latest as $row): ?><tr><td><?= h((string)$row['id']) ?></td><td><?= h((string)$row['customer_name']) ?></td><td><?= h((string)$row['driver_name']) ?></td><td><?= h((string)$row['vehicle_plate']) ?></td><td><?= h((string)$row['started_at']) ?></td><td><?= h((string)$row['price']) ?></td><td><a href="?booking_id=<?= h((string)$row['id']) ?>">preview</a></td></tr><?php endforeach; ?>
  <?php if (!$latest): ?><tr><td colspan="7" class="text-muted">No bolt_mail bookings found.</td></tr><?php endif; ?>
  </tbody></table></div></div></div>

  <div class="card mb-3"><div class="card-body"><h2 class="h5">Latest AADE receipt attempts</h2><div class="table-responsive"><table class="table table-sm table-striped small-table"><thead><tr><th>ID</th><th>Status</th><th>HTTP</th><th>Booking</th><th>Total</th><th>MARK</th><th>Created</th></tr></thead><tbody>
  <?php foreach ($latestAttempts as $row): ?><tr><td><?= h((string)$row['id']) ?></td><td><?= h((string)$row['provider_status']) ?></td><td><?= h((string)$row['http_status']) ?></td><td><?= h((string)$row['normalized_booking_id']) ?></td><td><?= h((string)$row['total_amount']) ?></td><td><?= h((string)($row['mark'] ?? '')) ?></td><td><?= h((string)$row['created_at']) ?></td></tr><?php endforeach; ?>
  <?php if (!$latestAttempts): ?><tr><td colspan="7" class="text-muted">No AADE receipt attempts found.</td></tr><?php endif; ?>
  </tbody></table></div></div></div>

  <div class="card"><div class="card-body"><h2 class="h5">Commands</h2><pre class="mono small mb-0"><?= h(json_encode($out['commands'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre></div></div>
</div>
</body>
</html><?php

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** @return array<string,mixed> */
function build_config_gate(Config $config): array
{
    return [
        'receipts_mode' => (string)$config->get('receipts.mode', 'MISSING'),
        'driver_receipt_copy_enabled' => (bool)$config->get('mail.driver_notifications.receipt_copy_enabled', false),
        'driver_receipt_pdf_mode' => (string)$config->get('mail.driver_notifications.receipt_pdf_mode', 'MISSING'),
        'aade_enabled' => (bool)$config->get('receipts.aade_mydata.enabled', false),
        'aade_environment' => (string)$config->get('receipts.aade_mydata.environment', 'MISSING'),
        'allow_send_invoices' => (bool)$config->get('receipts.aade_mydata.allow_send_invoices', false),
        'manual_confirm_phrase_configured' => trim((string)$config->get('receipts.aade_mydata.manual_send_confirm_phrase', '')) !== '',
    ];
}

/**
 * @param array<string,mixed> $summary
 * @return array<int,array<string,mixed>>
 */
function build_accountant_review_checklist(array $summary): array
{
    return [
        ['item'=>'Document type','configured_value'=>(string)($summary['document_type'] ?? ''),'needs_accountant_confirmation'=>true,'note'=>'Confirm this is correct for the ΑΠΥ/retail transfer workflow.'],
        ['item'=>'VAT category and rate','configured_value'=>'vat_category='.(string)($summary['vat_category'] ?? '').', rate='.(string)($summary['vat_rate_percent'] ?? '').'%','needs_accountant_confirmation'=>true,'note'=>'Confirm 13% VAT AADE category.'],
        ['item'=>'Payment method type','configured_value'=>(string)($summary['payment_method_type'] ?? ''),'needs_accountant_confirmation'=>true,'note'=>'Confirm payment method for Bolt/customer payment flow.'],
        ['item'=>'Income classification','configured_value'=>(string)($summary['income_classification_type'] ?? '').' / '.(string)($summary['income_classification_category'] ?? ''),'needs_accountant_confirmation'=>true,'note'=>'Confirm with accountant.'],
        ['item'=>'Series and AA','configured_value'=>(string)($summary['series'] ?? '').' / '.(string)($summary['aa'] ?? ''),'needs_accountant_confirmation'=>true,'note'=>'Confirm no collision with other receipt numbering.'],
        ['item'=>'Amounts','configured_value'=>'net='.(string)($summary['net_amount'] ?? '').', vat='.(string)($summary['vat_amount'] ?? '').', gross='.(string)($summary['gross_amount'] ?? ''),'needs_accountant_confirmation'=>false,'note'=>'Two-decimal values; gross must equal net + VAT.'],
    ];
}

/** @return array<int,array<string,mixed>> */
function latest_bolt_mail_bookings(Database $db): array
{
    try {
        return $db->fetchAll("SELECT id, customer_name, driver_name, vehicle_plate, started_at, price, created_at FROM normalized_bookings WHERE source='bolt_mail' ORDER BY id DESC LIMIT 20");
    } catch (Throwable) {
        return [];
    }
}

/** @return array<int,array<string,mixed>> */
function latest_receipt_attempts(Database $db): array
{
    try {
        return $db->fetchAll("SELECT id, provider_status, http_status, normalized_booking_id, total_amount, mark, created_at FROM receipt_issuance_attempts WHERE provider='aade_mydata' ORDER BY id DESC LIMIT 20");
    } catch (Throwable) {
        return [];
    }
}
