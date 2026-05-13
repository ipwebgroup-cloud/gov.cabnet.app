<?php
/**
 * gov.cabnet.app — V3 helper fill callback endpoint
 *
 * Purpose:
 * - Receive fill-only progress reports from the isolated V3 Firefox helper.
 * - Record V3-only queue events for visibility/debugging.
 *
 * Safety:
 * - Does not call EDXEIX.
 * - Does not call AADE.
 * - Does not write to production submission_jobs or submission_attempts.
 * - Does not modify /ops/pre-ride-email-tool.php.
 * - Does not change queue_status; it only inserts V3 queue event rows.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = [
    'https://edxeix.yme.gov.gr',
    'https://gov.cabnet.app',
];
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 600');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function v3hc_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function v3hc_private_file(string $relative): string
{
    $relative = ltrim($relative, '/');
    $candidates = [
        dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . $relative,
        dirname(__DIR__, 2) . '/gov.cabnet.app_app/' . $relative,
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            return $file;
        }
    }
    return $candidates[0];
}

function v3hc_app_context(?string &$error = null): ?array
{
    $bootstrap = v3hc_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) {
        $error = 'Private app bootstrap not found.';
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            $error = 'Private app bootstrap did not return a usable DB context.';
            return null;
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function v3hc_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function v3hc_trim_string($value, int $max = 500): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }
    return mb_substr($text, 0, $max, 'UTF-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    v3hc_json(['ok' => false, 'error' => 'POST required.'], 405);
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || trim($raw) === '') {
    v3hc_json(['ok' => false, 'error' => 'Empty request body.'], 400);
}
if (strlen($raw) > 65535) {
    v3hc_json(['ok' => false, 'error' => 'Request body is too large.'], 413);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    v3hc_json(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
}

$queueId = (int)($data['queueId'] ?? $data['queue_id'] ?? 0);
$dedupeKey = v3hc_trim_string($data['dedupeKey'] ?? $data['dedupe_key'] ?? '', 80);
$eventType = v3hc_trim_string($data['eventType'] ?? $data['event_type'] ?? 'helper_fill_reported', 80);
$eventStatus = v3hc_trim_string($data['eventStatus'] ?? $data['event_status'] ?? $data['status'] ?? 'ok', 40);
$message = v3hc_trim_string($data['message'] ?? '', 1000);

$allowedEventTypes = [
    'helper_fill_started',
    'helper_redirect_company',
    'helper_fill_completed',
    'helper_fill_failed',
    'helper_diagnostic_reported',
];
if (!in_array($eventType, $allowedEventTypes, true)) {
    $eventType = 'helper_diagnostic_reported';
}
if (!preg_match('/^[a-z0-9_\-]{1,40}$/i', $eventStatus)) {
    $eventStatus = 'ok';
}

if ($queueId <= 0 || $dedupeKey === '') {
    v3hc_json(['ok' => false, 'error' => 'queueId and dedupeKey are required.'], 400);
}

$ctxError = null;
$ctx = v3hc_app_context($ctxError);
if (!$ctx) {
    v3hc_json(['ok' => false, 'error' => $ctxError ?: 'DB context unavailable.'], 500);
}

try {
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    if (!v3hc_table_exists($db, 'pre_ride_email_v3_queue') || !v3hc_table_exists($db, 'pre_ride_email_v3_queue_events')) {
        v3hc_json(['ok' => false, 'error' => 'V3 queue schema is not installed.'], 500);
    }

    $stmt = $db->prepare('SELECT id, dedupe_key, queue_status, pickup_datetime FROM pre_ride_email_v3_queue WHERE id = ? AND dedupe_key = ? LIMIT 1');
    if (!$stmt) {
        v3hc_json(['ok' => false, 'error' => 'Could not prepare queue lookup.'], 500);
    }
    $stmt->bind_param('is', $queueId, $dedupeKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!is_array($row)) {
        v3hc_json(['ok' => false, 'error' => 'V3 queue row was not found or dedupe key did not match.'], 404);
    }

    $results = $data['results'] ?? [];
    if (is_array($results)) {
        $results = array_slice(array_map(static fn($v): string => v3hc_trim_string($v, 300), $results), 0, 80);
    } else {
        $results = [];
    }

    $context = [
        'helper_version' => v3hc_trim_string($data['helperVersion'] ?? $data['helper_version'] ?? '', 80),
        'page_url' => v3hc_trim_string($data['pageUrl'] ?? $data['page_url'] ?? '', 1000),
        'location_host' => v3hc_trim_string($data['locationHost'] ?? $data['location_host'] ?? '', 190),
        'saved_at' => v3hc_trim_string($data['savedAt'] ?? $data['saved_at'] ?? '', 80),
        'reported_at' => date(DATE_ATOM),
        'queue_status_at_report' => (string)($row['queue_status'] ?? ''),
        'pickup_datetime_at_report' => (string)($row['pickup_datetime'] ?? ''),
        'result_count' => count($results),
        'results' => $results,
        'user_agent' => v3hc_trim_string($_SERVER['HTTP_USER_AGENT'] ?? '', 300),
        'remote_addr_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
    ];
    $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($contextJson)) {
        $contextJson = '{}';
    }

    if ($message === '') {
        $message = match ($eventType) {
            'helper_fill_started' => 'V3 helper started fill-only operation.',
            'helper_redirect_company' => 'V3 helper redirected to the correct EDXEIX lessor form.',
            'helper_fill_completed' => 'V3 helper completed fill-only operation. Operator must still review and save manually.',
            'helper_fill_failed' => 'V3 helper reported fill-only failure.',
            default => 'V3 helper reported diagnostic event.',
        };
    }

    $createdBy = 'v3_firefox_helper';
    $insert = $db->prepare('INSERT INTO pre_ride_email_v3_queue_events (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$insert) {
        v3hc_json(['ok' => false, 'error' => 'Could not prepare event insert.'], 500);
    }
    $insert->bind_param('issssss', $queueId, $dedupeKey, $eventType, $eventStatus, $message, $contextJson, $createdBy);
    $insert->execute();

    v3hc_json([
        'ok' => true,
        'version' => 'v3.0.16-helper-fill-callback',
        'queue_id' => $queueId,
        'dedupe_key' => $dedupeKey,
        'event_id' => $db->insert_id,
        'event_type' => $eventType,
        'event_status' => $eventStatus,
        'safety' => [
            'v3_events_only' => true,
            'queue_status_changed' => false,
            'edxeix_call' => false,
            'aade_call' => false,
            'production_submission_jobs' => false,
            'production_submission_attempts' => false,
        ],
    ]);
} catch (Throwable $e) {
    v3hc_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
