<?php

declare(strict_types=1);

use Bridge\Config;
use Bridge\Database;
use Bridge\Receipts\AadeMyDataClient;

$bootstrap = require dirname(__DIR__) . '/src/bootstrap.php';
/** @var Config $config */
$config = $bootstrap['config'];
/** @var Database $db */
$db = $bootstrap['db'];

$opts = getopt('', ['ping', 'mark::', 'record', 'by::']);
$doPing = array_key_exists('ping', $opts);
$record = array_key_exists('record', $opts);
$mark = isset($opts['mark']) ? max(1, (int)$opts['mark']) : 1;
$by = is_string($opts['by'] ?? null) ? (string)$opts['by'] : 'cli';

$aadeConfig = $config->get('receipts.aade_mydata', []);
$receiptsMode = (string)$config->get('receipts.mode', 'MISSING');
$driverReceiptEnabled = (bool)$config->get('mail.driver_notifications.receipt_copy_enabled', false);
$driverReceiptMode = (string)$config->get('mail.driver_notifications.receipt_pdf_mode', 'MISSING');

$client = new AadeMyDataClient(is_array($aadeConfig) ? $aadeConfig : []);
$readiness = $client->readiness();

$payload = [
    'ok' => true,
    'script' => 'cli/aade_mydata_readiness.php',
    'generated_at' => date('c'),
    'receipts_mode' => $receiptsMode,
    'driver_receipt_copy_enabled' => $driverReceiptEnabled,
    'driver_receipt_pdf_mode' => $driverReceiptMode,
    'aade' => $readiness,
    'ping_requested' => $doPing,
    'ping' => null,
    'safety' => [
        'no_secrets_printed' => true,
        'does_not_send_invoices' => true,
        'does_not_email_receipts' => true,
        'does_not_call_edxeix' => true,
        'does_not_create_jobs_or_attempts' => true,
        'aade_response_excerpts_suppressed' => true,
    ],
];

if ($doPing) {
    try {
        $result = $client->pingRequestTransmittedDocs($mark);
        $payload['ping'] = $result;
        if ($record) {
            record_attempt($db, $readiness, $result, $by);
        }
    } catch (Throwable $e) {
        $result = [
            'ok' => false,
            'http_status' => 0,
            'error' => $e->getMessage(),
            'response_excerpt_suppressed' => true,
            'response_bytes' => 0,
            'response_sha256' => hash('sha256', ''),
        ];
        $payload['ok'] = false;
        $payload['ping'] = $result;
        if ($record) {
            record_attempt($db, $readiness, $result, $by);
        }
    }
}

function record_attempt(Database $db, array $readiness, array $result, string $by): void
{
    try {
        $hasTable = $db->fetchOne("SHOW TABLES LIKE 'receipt_issuance_attempts'");
        if (!is_array($hasTable)) {
            return;
        }

        $status = !empty($result['ok']) ? 'connectivity_ok' : 'connectivity_failed';
        $response = json_encode([
            'ok' => (bool)($result['ok'] ?? false),
            'http_status' => (int)($result['http_status'] ?? 0),
            'error' => $result['error'] ?? null,
            'response_excerpt_suppressed' => true,
            'response_bytes' => (int)($result['response_bytes'] ?? 0),
            'response_sha256' => $result['response_sha256'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $db->insert(
            'INSERT INTO receipt_issuance_attempts (provider, environment, receipt_mode, provider_status, issuer_vat_number, http_status, response_payload_json, error_message, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())',
            [
                'aade_mydata',
                (string)($readiness['environment'] ?? 'test'),
                'aade_mydata',
                $status,
                (string)($readiness['issuer_vat_number'] ?? ''),
                (int)($result['http_status'] ?? 0),
                $response,
                (string)($result['error'] ?? ''),
                $by,
            ],
            'sssssisss'
        );
    } catch (Throwable) {
        // Readiness and ping output must not fail because audit logging failed.
    }
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
