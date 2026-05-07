<?php

declare(strict_types=1);

use Bridge\Config;
use Bridge\Database;
use Bridge\Receipts\AadeMyDataClient;

$bootstrap = require '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php';
/** @var Config $config */
$config = $bootstrap['config'];
/** @var Database $db */
$db = $bootstrap['db'];

$aadeConfig = $config->get('receipts.aade_mydata', []);
$client = new AadeMyDataClient(is_array($aadeConfig) ? $aadeConfig : []);
$readiness = $client->readiness();

$driverReceiptEnabled = (bool)$config->get('mail.driver_notifications.receipt_copy_enabled', false);
$driverReceiptMode = (string)$config->get('mail.driver_notifications.receipt_pdf_mode', 'MISSING');
$receiptMode = (string)$config->get('receipts.mode', 'MISSING');

$tableExists = table_exists($db, 'receipt_issuance_attempts');
$lastAttempts = $tableExists ? $db->fetchAll('SELECT id, provider, environment, provider_status, http_status, issuer_vat_number, mark, uid, error_code, LEFT(error_message, 240) AS error_message, created_by, created_at FROM receipt_issuance_attempts ORDER BY id DESC LIMIT 10') : [];
$attemptCounts = $tableExists ? key_counts($db, 'receipt_issuance_attempts', 'provider_status') : [];

$gateChecks = [
    [
        'label' => 'Receipt mode is AADE/myDATA',
        'ok' => $receiptMode === 'aade_mydata',
        'detail' => 'receipts.mode should be aade_mydata before official receipt work.',
    ],
    [
        'label' => 'Generated receipt emails are disabled',
        'ok' => $driverReceiptEnabled === false,
        'detail' => 'mail.driver_notifications.receipt_copy_enabled must remain false until AADE issuance succeeds.',
    ],
    [
        'label' => 'Driver receipt mode points to AADE/myDATA',
        'ok' => $driverReceiptMode === 'aade_mydata',
        'detail' => 'mail.driver_notifications.receipt_pdf_mode should be aade_mydata.',
    ],
    [
        'label' => 'AADE credentials are present',
        'ok' => !empty($readiness['user_id_present']) && !empty($readiness['subscription_key_present']),
        'detail' => 'Only presence and key lengths are shown; secret values are never displayed.',
    ],
    [
        'label' => 'AADE issuer VAT is configured',
        'ok' => trim((string)($readiness['issuer_vat_number'] ?? '')) !== '',
        'detail' => 'Issuer VAT should be the LUXLIMO ΑΦΜ in server-only config.',
    ],
    [
        'label' => 'Receipt issuance audit table exists',
        'ok' => $tableExists,
        'detail' => 'receipt_issuance_attempts stores connectivity/issuance audit without secrets.',
    ],
];

$hardOk = true;
foreach ($gateChecks as $check) {
    if (empty($check['ok'])) {
        $hardOk = false;
        break;
    }
}

$verdict = $hardOk ? 'AADE_MYDATA_READY_FOR_CONNECTIVITY_TEST' : 'AADE_MYDATA_CONFIG_INCOMPLETE';

$out = [
    'ok' => true,
    'script' => 'ops/aade-mydata-readiness.php',
    'generated_at' => date('c'),
    'verdict' => $verdict,
    'safety_contract' => [
        'read_only' => true,
        'displays_secrets' => false,
        'writes_files' => false,
        'imports_mail' => false,
        'sends_driver_email' => false,
        'creates_normalized_bookings' => false,
        'creates_dry_run_evidence' => false,
        'creates_submission_jobs' => false,
        'creates_submission_attempts' => false,
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'calls_aade' => false,
        'live_edxeix_submission' => false,
        'aade_response_excerpts_suppressed' => true,
    ],
    'config' => [
        'receipts_mode' => $receiptMode,
        'driver_receipt_copy_enabled' => $driverReceiptEnabled,
        'driver_receipt_pdf_mode' => $driverReceiptMode,
        'aade_enabled' => (bool)($readiness['enabled'] ?? false),
        'environment' => $readiness['environment'] ?? 'MISSING',
        'endpoint_base' => $readiness['endpoint_base'] ?? '',
        'send_invoices_url' => $readiness['send_invoices_url'] ?? '',
        'request_transmitted_docs_url' => $readiness['request_transmitted_docs_url'] ?? '',
        'user_id_present' => $readiness['user_id_present'] ?? false,
        'user_id_length' => $readiness['user_id_length'] ?? 0,
        'subscription_key_present' => $readiness['subscription_key_present'] ?? false,
        'subscription_key_length' => $readiness['subscription_key_length'] ?? 0,
        'issuer_vat_number' => $readiness['issuer_vat_number'] ?? '',
        'issuer_name_present' => $readiness['issuer_name_present'] ?? false,
    ],
    'audit' => [
        'receipt_issuance_attempts_table' => $tableExists,
        'counts_by_status' => $attemptCounts,
        'latest_attempts' => $lastAttempts,
    ],
    'gate_checks' => $gateChecks,
    'commands' => [
        'install_sql' => 'DB_NAME=$(php -r \'$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];\') && mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_07_receipt_issuance_attempts.sql',
        'readiness_cli' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php',
        'connectivity_ping_record' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php --ping --record --by=Andreas',
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
<title>AADE/myDATA Readiness</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f6f8fb}.card{border:0;box-shadow:0 8px 24px rgba(15,23,42,.08)}code{white-space:normal}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}.small-table td,.small-table th{font-size:.88rem}.badge-ok{background:#198754}.badge-bad{background:#dc3545}.badge-warn{background:#ffc107;color:#111}
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h1 class="h3 mb-1">AADE/myDATA Readiness</h1>
      <div class="text-muted">Generated <?= h($out['generated_at']) ?></div>
    </div>
    <span class="badge <?= $hardOk ? 'badge-ok' : 'badge-warn' ?> fs-6"><?= h($verdict) ?></span>
  </div>

  <div class="alert alert-info">
    This page is read-only. It does not display credentials and does not call AADE. Use the CLI ping command for connectivity testing.
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card"><div class="card-body"><h2 class="h5">Config</h2><pre class="mono small mb-0"><?= h(json_encode($out['config'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body"><h2 class="h5">Gate checks</h2><?php foreach ($gateChecks as $check): ?><div class="d-flex gap-2 mb-2"><span class="badge <?= $check['ok'] ? 'badge-ok' : 'badge-bad' ?>"><?= $check['ok'] ? 'OK' : 'BLOCK' ?></span><div><strong><?= h($check['label']) ?></strong><br><span class="text-muted small"><?= h($check['detail']) ?></span></div></div><?php endforeach; ?></div></div></div>
  </div>

  <div class="card mb-3"><div class="card-body"><h2 class="h5">Commands</h2><pre class="mono small mb-0"><?= h(json_encode($out['commands'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre></div></div>

  <div class="card"><div class="card-body"><h2 class="h5">Latest audit attempts</h2>
    <div class="table-responsive"><table class="table table-sm table-striped small-table"><thead><tr><th>ID</th><th>Status</th><th>HTTP</th><th>Environment</th><th>Error</th><th>Created</th></tr></thead><tbody>
    <?php foreach ($lastAttempts as $row): ?><tr><td><?= h((string)$row['id']) ?></td><td><?= h((string)$row['provider_status']) ?></td><td><?= h((string)$row['http_status']) ?></td><td><?= h((string)$row['environment']) ?></td><td><?= h((string)$row['error_message']) ?></td><td><?= h((string)$row['created_at']) ?></td></tr><?php endforeach; ?>
    <?php if (!$lastAttempts): ?><tr><td colspan="6" class="text-muted">No audit attempts recorded yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div></div>
</div>
</body>
</html><?php

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function table_exists(Database $db, string $table): bool
{
    try {
        return is_array($db->fetchOne("SHOW TABLES LIKE ?", [$table], 's'));
    } catch (Throwable) {
        return false;
    }
}

/** @return array<string,int> */
function key_counts(Database $db, string $table, string $column): array
{
    try {
        $rows = $db->fetchAll('SELECT `' . str_replace('`', '``', $column) . '` AS k, COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '` GROUP BY `' . str_replace('`', '``', $column) . '` ORDER BY c DESC');
    } catch (Throwable) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[(string)($row['k'] ?? '')] = (int)($row['c'] ?? 0);
    }
    return $out;
}
