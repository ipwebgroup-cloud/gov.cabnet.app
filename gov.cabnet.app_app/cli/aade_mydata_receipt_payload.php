<?php

declare(strict_types=1);

use Bridge\Config;
use Bridge\Database;
use Bridge\Receipts\AadeMyDataClient;
use Bridge\Receipts\AadeReceiptPayloadBuilder;

$bootstrap = require dirname(__DIR__) . '/src/bootstrap.php';
/** @var Config $config */
$config = $bootstrap['config'];
/** @var Database $db */
$db = $bootstrap['db'];

$opts = getopt('', [
    'booking-id:',
    'show-xml',
    'record-prepared',
    'send',
    'confirm:',
    'by::',
]);

$bookingId = isset($opts['booking-id']) ? (int)$opts['booking-id'] : 0;
$showXml = array_key_exists('show-xml', $opts);
$recordPrepared = array_key_exists('record-prepared', $opts);
$send = array_key_exists('send', $opts);
$confirm = is_string($opts['confirm'] ?? null) ? (string)$opts['confirm'] : '';
$by = is_string($opts['by'] ?? null) ? (string)$opts['by'] : 'cli';

$out = [
    'ok' => false,
    'script' => 'cli/aade_mydata_receipt_payload.php',
    'generated_at' => date('c'),
    'safety' => [
        'does_not_email_receipts' => true,
        'does_not_call_edxeix' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'send_invoices_requires_allow_config' => true,
        'send_invoices_requires_confirm_phrase' => true,
        'send_invoices_duplicate_protected' => true,
        'raw_aade_response_not_printed' => true,
    ],
];

try {
    if ($bookingId <= 0) {
        throw new RuntimeException('Missing required --booking-id=ID');
    }

    $builder = new AadeReceiptPayloadBuilder($config, $db);
    $built = $builder->buildForBookingId($bookingId);
    $summary = $built['summary'];
    $validation = $built['validation'];
    $xml = (string)$built['xml'];
    $xmlHash = (string)$built['xml_sha256'];

    $configGate = build_config_gate($config);
    $duplicateGate = build_duplicate_gate($db, $bookingId, $xmlHash);
    $firstSendGate = build_first_send_gate($config, $confirm, $validation, $duplicateGate);

    $out['ok'] = true;
    $out['mode'] = $send ? 'send_requested' : 'preview_only';
    $out['booking_id'] = $bookingId;
    $out['summary'] = $summary;
    $out['validation'] = $validation;
    $out['config_gate'] = $configGate;
    $out['duplicate_gate'] = $duplicateGate;
    $out['first_send_gate'] = $firstSendGate;
    $out['accountant_review_checklist'] = build_accountant_review_checklist($summary);
    $out['send_invoices_status'] = send_status_label($configGate, $firstSendGate, $send);
    $out['xml_sha256'] = $xmlHash;
    $out['xml_bytes'] = $built['xml_bytes'];
    $out['xml_included'] = $showXml;

    if ($showXml) {
        $out['xml'] = $xml;
    }

    if ($recordPrepared) {
        $out['prepared_attempt_id'] = record_attempt($db, $summary, 'prepared', null, 0, $xml, null, '', $by, $configGate['aade_environment']);
    }

    if ($send) {
        if (!empty($firstSendGate['blockers'])) {
            $out['ok'] = false;
            $out['send'] = [
                'attempted' => false,
                'blockers' => $firstSendGate['blockers'],
                'raw_response_not_printed' => true,
            ];
            echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit(2);
        }

        $client = new AadeMyDataClient((array)$config->get('receipts.aade_mydata', []));
        $result = $client->sendInvoicesXml($xml);
        $responseBody = (string)($result['response_body'] ?? '');
        unset($result['response_body']);

        $parsed = parse_aade_response($responseBody);
        $status = (!empty($result['ok']) && empty($parsed['errors']) && (!empty($parsed['mark']) || !empty($parsed['uid'])))
            ? 'issued'
            : 'failed';

        $attemptId = record_attempt(
            $db,
            $summary,
            $status,
            $result,
            (int)($result['http_status'] ?? 0),
            $xml,
            $parsed,
            (string)($result['error'] ?? ''),
            $by,
            $configGate['aade_environment']
        );

        $out['send'] = [
            'attempted' => true,
            'attempt_id' => $attemptId,
            'provider_status' => $status,
            'http_status' => (int)($result['http_status'] ?? 0),
            'response_sha256' => $result['response_sha256'] ?? null,
            'response_bytes' => (int)($result['response_bytes'] ?? 0),
            'mark' => $parsed['mark'] ?? null,
            'uid' => $parsed['uid'] ?? null,
            'qr_url_present' => !empty($parsed['qr_url']),
            'errors_present' => !empty($parsed['errors']),
            'error_code' => $parsed['error_code'] ?? null,
            'error_message' => $parsed['error_message'] ?? null,
            'raw_response_not_printed' => true,
        ];
        $out['ok'] = $status === 'issued';
    }
} catch (Throwable $e) {
    $out['ok'] = false;
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/** @return array<string,mixed> */
function build_config_gate(Config $config): array
{
    $phrase = trim((string)$config->get('receipts.aade_mydata.manual_send_confirm_phrase', ''));
    return [
        'receipts_mode' => (string)$config->get('receipts.mode', 'MISSING'),
        'aade_enabled' => (bool)$config->get('receipts.aade_mydata.enabled', false),
        'aade_environment' => (string)$config->get('receipts.aade_mydata.environment', 'MISSING'),
        'allow_send_invoices' => (bool)$config->get('receipts.aade_mydata.allow_send_invoices', false),
        'manual_confirm_phrase_configured' => $phrase !== '',
        'manual_confirm_phrase_hint' => $phrase !== '' ? 'configured_server_side_not_printed' : 'missing',
        'driver_receipt_copy_enabled' => (bool)$config->get('mail.driver_notifications.receipt_copy_enabled', false),
        'driver_receipt_pdf_mode' => (string)$config->get('mail.driver_notifications.receipt_pdf_mode', 'MISSING'),
        'safe_for_preview' => true,
        'safe_for_send_config' => (bool)$config->get('receipts.aade_mydata.allow_send_invoices', false)
            && (string)$config->get('receipts.mode', '') === 'aade_mydata'
            && (bool)$config->get('receipts.aade_mydata.enabled', false)
            && (string)$config->get('mail.driver_notifications.receipt_pdf_mode', '') === 'aade_mydata'
            && !(bool)$config->get('mail.driver_notifications.receipt_copy_enabled', false),
    ];
}

/** @return array<string,mixed> */
function build_duplicate_gate(Database $db, int $bookingId, string $xmlHash): array
{
    $out = [
        'booking_id' => $bookingId,
        'xml_hash' => $xmlHash,
        'issued_for_booking' => 0,
        'issued_for_xml_hash' => 0,
        'blocked' => false,
        'blockers' => [],
    ];

    try {
        $row = $db->fetchOne("SELECT COUNT(*) AS c FROM receipt_issuance_attempts WHERE normalized_booking_id=? AND provider='aade_mydata' AND provider_status='issued'", [$bookingId], 'i');
        $out['issued_for_booking'] = (int)($row['c'] ?? 0);
    } catch (Throwable) {
        $out['blockers'][] = 'duplicate_booking_check_unavailable';
    }

    try {
        $row = $db->fetchOne("SELECT COUNT(*) AS c FROM receipt_issuance_attempts WHERE request_payload_hash=? AND provider='aade_mydata' AND provider_status='issued'", [$xmlHash], 's');
        $out['issued_for_xml_hash'] = (int)($row['c'] ?? 0);
    } catch (Throwable) {
        $out['blockers'][] = 'duplicate_payload_hash_check_unavailable';
    }

    if ((int)$out['issued_for_booking'] > 0) {
        $out['blockers'][] = 'already_issued_for_booking';
    }
    if ((int)$out['issued_for_xml_hash'] > 0) {
        $out['blockers'][] = 'already_issued_for_payload_hash';
    }

    $out['blockers'] = array_values(array_unique($out['blockers']));
    $out['blocked'] = $out['blockers'] !== [];
    return $out;
}

/**
 * @param array<string,mixed> $validation
 * @param array<string,mixed> $duplicateGate
 * @return array<string,mixed>
 */
function build_first_send_gate(Config $config, string $confirm, array $validation, array $duplicateGate): array
{
    $phrase = trim((string)$config->get('receipts.aade_mydata.manual_send_confirm_phrase', 'I UNDERSTAND SEND AADE MYDATA PRODUCTION RECEIPT'));
    $blockers = [];

    if ((string)$config->get('receipts.mode', '') !== 'aade_mydata') {
        $blockers[] = 'receipts_mode_not_aade_mydata';
    }
    if (!(bool)$config->get('receipts.aade_mydata.enabled', false)) {
        $blockers[] = 'aade_mydata_not_enabled';
    }
    if (!(bool)$config->get('receipts.aade_mydata.allow_send_invoices', false)) {
        $blockers[] = 'allow_send_invoices_not_enabled_in_config';
    }
    if ($phrase === '') {
        $blockers[] = 'manual_confirm_phrase_not_configured';
    }
    if ($confirm !== $phrase) {
        $blockers[] = 'confirm_phrase_missing_or_invalid';
    }
    if ((string)$config->get('mail.driver_notifications.receipt_pdf_mode', '') !== 'aade_mydata') {
        $blockers[] = 'driver_receipt_pdf_mode_not_aade_mydata';
    }
    if ((bool)$config->get('mail.driver_notifications.receipt_copy_enabled', false)) {
        $blockers[] = 'driver_receipt_copy_must_remain_disabled_for_first_send';
    }
    if (empty($validation['ok_for_send_if_confirmed'])) {
        foreach (($validation['blockers'] ?? []) as $b) {
            $blockers[] = (string)$b;
        }
    }
    foreach (($duplicateGate['blockers'] ?? []) as $b) {
        $blockers[] = (string)$b;
    }

    $blockers = array_values(array_unique($blockers));
    return [
        'ready_to_send_if_requested' => $blockers === [],
        'blockers' => $blockers,
        'requires_manual_confirm_phrase' => true,
        'raw_response_printed' => false,
    ];
}

/** @param array<string,mixed> $configGate @param array<string,mixed> $firstSendGate */
function send_status_label(array $configGate, array $firstSendGate, bool $sendRequested): string
{
    if (!empty($firstSendGate['blockers'])) {
        if (empty($configGate['allow_send_invoices'])) {
            return 'DISABLED_IN_CONFIG_PREVIEW_ONLY';
        }
        return $sendRequested ? 'SEND_REQUEST_BLOCKED_BY_GATE' : 'SEND_CONFIG_ENABLED_AWAITING_VALID_CONFIRMATION';
    }
    return $sendRequested ? 'SEND_REQUEST_GATE_PASSED_ATTEMPTING_AADE' : 'SEND_CONFIG_ENABLED_READY_FOR_MANUAL_CONFIRMATION';
}

/**
 * @param array<string,mixed> $summary
 * @return array<int,array<string,mixed>>
 */
function build_accountant_review_checklist(array $summary): array
{
    return [
        [
            'item' => 'Document type',
            'configured_value' => (string)($summary['document_type'] ?? ''),
            'needs_accountant_confirmation' => true,
            'note' => 'Confirm this is the correct AADE/myDATA invoice type for the transfer receipt / ΑΠΥ workflow.',
        ],
        [
            'item' => 'VAT category and rate',
            'configured_value' => 'vat_category=' . (string)($summary['vat_category'] ?? '') . ', rate=' . (string)($summary['vat_rate_percent'] ?? '') . '%',
            'needs_accountant_confirmation' => true,
            'note' => 'Confirm AADE VAT category for 13% passenger transfer/tourist office service.',
        ],
        [
            'item' => 'Payment method type',
            'configured_value' => (string)($summary['payment_method_type'] ?? ''),
            'needs_accountant_confirmation' => true,
            'note' => 'Confirm AADE payment method code for Bolt/customer payment flow.',
        ],
        [
            'item' => 'Income classification',
            'configured_value' => (string)($summary['income_classification_type'] ?? '') . ' / ' . (string)($summary['income_classification_category'] ?? ''),
            'needs_accountant_confirmation' => true,
            'note' => 'Confirm E3 classification and category with accountant before first SendInvoices.',
        ],
        [
            'item' => 'Series and AA numbering',
            'configured_value' => (string)($summary['series'] ?? '') . ' / ' . (string)($summary['aa'] ?? ''),
            'needs_accountant_confirmation' => true,
            'note' => 'Confirm dedicated AADE series and numbering strategy. Avoid duplicate AA values.',
        ],
        [
            'item' => 'Amounts',
            'configured_value' => 'net=' . (string)($summary['net_amount'] ?? '') . ', vat=' . (string)($summary['vat_amount'] ?? '') . ', gross=' . (string)($summary['gross_amount'] ?? ''),
            'needs_accountant_confirmation' => false,
            'note' => 'Values are formatted to two decimals and gross must equal net + VAT.',
        ],
    ];
}

/**
 * @param array<string,mixed> $summary
 * @param array<string,mixed>|null $transport
 * @param array<string,mixed>|null $parsed
 */
function record_attempt(Database $db, array $summary, string $status, ?array $transport, int $httpStatus, string $xml, ?array $parsed, string $error, string $by, string $environment): int
{
    try {
        $hasTable = $db->fetchOne("SHOW TABLES LIKE 'receipt_issuance_attempts'");
        if (!is_array($hasTable)) {
            return 0;
        }

        $requestMeta = json_encode([
            'xml_sha256' => hash('sha256', $xml),
            'xml_bytes' => strlen($xml),
            'payload_body_not_stored' => true,
            'summary' => $summary,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $responseMeta = json_encode([
            'http_status' => $httpStatus,
            'response_excerpt_suppressed' => true,
            'response_sha256' => $transport['response_sha256'] ?? null,
            'response_bytes' => $transport['response_bytes'] ?? null,
            'parsed' => $parsed,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $db->insert(
            'INSERT INTO receipt_issuance_attempts (intake_id, normalized_booking_id, source, provider, environment, receipt_mode, provider_status, issuer_vat_number, document_type, total_amount, net_amount, vat_amount, vat_rate, http_status, mark, uid, qr_url, request_payload_hash, request_payload_json, response_payload_json, error_code, error_message, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
            [
                $summary['intake_id'] ?? null,
                (int)($summary['booking_id'] ?? 0),
                (string)($summary['source'] ?? 'bolt_mail'),
                'aade_mydata',
                $environment !== '' ? $environment : 'production',
                'aade_mydata',
                $status,
                (string)($summary['issuer_vat_number'] ?? ''),
                (string)($summary['document_type'] ?? ''),
                (float)($summary['gross_amount'] ?? 0),
                (float)($summary['net_amount'] ?? 0),
                (float)($summary['vat_amount'] ?? 0),
                (float)($summary['vat_rate_percent'] ?? 0),
                $httpStatus,
                $parsed['mark'] ?? null,
                $parsed['uid'] ?? null,
                $parsed['qr_url'] ?? null,
                hash('sha256', $xml),
                $requestMeta,
                $responseMeta,
                $parsed['error_code'] ?? null,
                $error !== '' ? $error : (string)($parsed['error_message'] ?? ''),
                $by,
            ],
            'iisssssssddddisssssssss'
        );
    } catch (Throwable) {
        return 0;
    }
}

/** @return array<string,mixed> */
function parse_aade_response(string $xml): array
{
    $out = [
        'mark' => null,
        'uid' => null,
        'qr_url' => null,
        'errors' => [],
        'error_code' => null,
        'error_message' => null,
    ];

    if (trim($xml) === '') {
        $out['errors'][] = 'empty_response';
        $out['error_message'] = 'AADE returned an empty response body.';
        return $out;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadXML($xml)) {
        $out['errors'][] = 'invalid_xml_response';
        $out['error_message'] = 'AADE response was not valid XML.';
        libxml_clear_errors();
        return $out;
    }
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $read = static function (string $local) use ($xpath): ?string {
        $nodes = $xpath->query('//*[local-name()="' . $local . '"]');
        if ($nodes && $nodes->length > 0) {
            $v = trim((string)$nodes->item(0)?->textContent);
            return $v !== '' ? $v : null;
        }
        return null;
    };

    $out['uid'] = $read('invoiceUid') ?? $read('uid');
    $out['mark'] = $read('invoiceMark') ?? $read('mark');
    $out['qr_url'] = $read('qrUrl') ?? $read('qrCodeUrl');
    $out['error_code'] = $read('code') ?? $read('errorCode');
    $out['error_message'] = $read('message') ?? $read('errorMessage');

    $errorNodes = $xpath->query('//*[local-name()="errors"]/* | //*[local-name()="error"]');
    if ($errorNodes) {
        foreach ($errorNodes as $node) {
            $txt = trim((string)$node->textContent);
            if ($txt !== '') {
                $out['errors'][] = mb_substr($txt, 0, 500, 'UTF-8');
            }
        }
    }

    return $out;
}
