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

    $out['ok'] = true;
    $out['mode'] = $send ? 'send_requested' : 'preview_only';
    $out['booking_id'] = $bookingId;
    $out['summary'] = $summary;
    $out['validation'] = $validation;
    $out['xml_sha256'] = $built['xml_sha256'];
    $out['xml_bytes'] = $built['xml_bytes'];
    $out['xml_included'] = $showXml;

    if ($showXml) {
        $out['xml'] = $xml;
    }

    if ($recordPrepared) {
        $out['prepared_attempt_id'] = record_attempt($db, $summary, 'prepared', null, 0, $xml, null, '', $by);
    }

    if ($send) {
        $allowSend = (bool)$config->get('receipts.aade_mydata.allow_send_invoices', false);
        $phrase = (string)$config->get('receipts.aade_mydata.manual_send_confirm_phrase', 'I UNDERSTAND SEND AADE MYDATA PRODUCTION RECEIPT');
        $sendBlockers = [];

        if (!$allowSend) {
            $sendBlockers[] = 'allow_send_invoices_not_enabled_in_config';
        }
        if ($confirm !== $phrase) {
            $sendBlockers[] = 'confirm_phrase_missing_or_invalid';
        }
        if (empty($validation['ok_for_send_if_confirmed'])) {
            foreach (($validation['blockers'] ?? []) as $b) {
                $sendBlockers[] = (string)$b;
            }
        }

        if ($sendBlockers !== []) {
            $out['ok'] = false;
            $out['send'] = [
                'attempted' => false,
                'blockers' => array_values(array_unique($sendBlockers)),
            ];
            echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit(2);
        }

        $client = new AadeMyDataClient((array)$config->get('receipts.aade_mydata', []));
        $result = $client->sendInvoicesXml($xml);
        $responseBody = (string)($result['response_body'] ?? '');
        unset($result['response_body']);

        $parsed = parse_aade_response($responseBody);
        $status = (!empty($result['ok']) && empty($parsed['errors'])) ? 'issued' : 'failed';
        $attemptId = record_attempt($db, $summary, $status, $result, (int)($result['http_status'] ?? 0), $xml, $parsed, (string)($result['error'] ?? ''), $by);

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
            'raw_response_not_printed' => true,
        ];
    }
} catch (Throwable $e) {
    $out['ok'] = false;
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @param array<string,mixed> $summary
 * @param array<string,mixed>|null $transport
 * @param array<string,mixed>|null $parsed
 */
function record_attempt(Database $db, array $summary, string $status, ?array $transport, int $httpStatus, string $xml, ?array $parsed, string $error, string $by): int
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
                'production',
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
