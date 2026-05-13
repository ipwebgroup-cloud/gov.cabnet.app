<?php
/**
 * gov.cabnet.app — Bolt Pre-Ride Email Tool v3 Isolated
 *
 * Independent route. Does not modify or include /ops/pre-ride-email-tool.php.
 * Uses v3-specific private classes under gov.cabnet.app_app/src/BoltMailV3/.
 *
 * Safety:
 * - No DB writes.
 * - No submission_jobs/submission_attempts creation.
 * - No server-side EDXEIX call.
 * - No AADE call.
 * - No raw email persistence.
 * - Optional V3 Firefox helper is fill-only and has no POST button.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const PE3_TOOL_VERSION = 'v3.0.4-isolated-queue-preview';
const PE3_MIN_FUTURE_MINUTES = 20;
const PE3_EDXEIX_CREATE_URL = 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create';

function pe3_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pe3_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . pe3_h($type) . '">' . pe3_h($text) . '</span>';
}

function pe3_private_file(string $relative): string
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

$parserFile = pe3_private_file('src/BoltMailV3/BoltPreRideEmailParserV3.php');
$lookupFile = pe3_private_file('src/BoltMailV3/EdxeixMappingLookupV3.php');
$mailLoaderFile = pe3_private_file('src/BoltMailV3/MaildirPreRideEmailLoaderV3.php');

foreach ([$parserFile, $lookupFile, $mailLoaderFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        http_response_code(500);
        echo 'V3 dependency not found: ' . pe3_h($requiredFile);
        exit;
    }
    require_once $requiredFile;
}

use Bridge\BoltMailV3\BoltPreRideEmailParserV3;
use Bridge\BoltMailV3\EdxeixMappingLookupV3;
use Bridge\BoltMailV3\MaildirPreRideEmailLoaderV3;

function pe3_app_bootstrap_path(): string
{
    return pe3_private_file('src/bootstrap.php');
}

function pe3_app_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;
    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = pe3_app_bootstrap_path();
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found; DB lookup is unavailable.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            $loadError = 'Private app bootstrap did not return a context array.';
            $error = $loadError;
            return null;
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

/** @param array<string,string> $fields @return array<string,mixed> */
function pe3_lookup_edxeix_ids(array $fields, ?string &$error = null): array
{
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'lessor_source' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'messages' => [],
        'warnings' => ['DB lookup was not available.'],
    ];

    $ctx = pe3_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return $empty;
    }

    try {
        $lookup = new EdxeixMappingLookupV3($ctx['db']->connection());
        $result = $lookup->lookup($fields);
        $error = null;
        return $result;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['V3 DB lookup failed: ' . $error];
        return $empty;
    }
}

/** @return array<int,string> */
function pe3_maildir_extra_dirs(): array
{
    $extraDirs = [];
    $ctxError = null;
    $ctx = pe3_app_context($ctxError);
    if (is_array($ctx) && isset($ctx['config']) && method_exists($ctx['config'], 'get')) {
        $single = $ctx['config']->get('mail.pre_ride_maildir_v3');
        if (is_string($single) && trim($single) !== '') {
            $extraDirs[] = trim($single);
        }
        $fallbackSingle = $ctx['config']->get('mail.pre_ride_maildir');
        if (is_string($fallbackSingle) && trim($fallbackSingle) !== '') {
            $extraDirs[] = trim($fallbackSingle);
        }
        $many = $ctx['config']->get('mail.pre_ride_maildirs_v3', []);
        if (is_array($many)) {
            foreach ($many as $dir) {
                if (is_string($dir) && trim($dir) !== '') {
                    $extraDirs[] = trim($dir);
                }
            }
        }
        $fallbackMany = $ctx['config']->get('mail.pre_ride_maildirs', []);
        if (is_array($fallbackMany)) {
            foreach ($fallbackMany as $dir) {
                if (is_string($dir) && trim($dir) !== '') {
                    $extraDirs[] = trim($dir);
                }
            }
        }
    }
    return array_values(array_unique($extraDirs));
}

/** @return array<string,mixed> */
function pe3_load_latest_server_email(): array
{
    $loader = new MaildirPreRideEmailLoaderV3();
    return $loader->loadLatest(pe3_maildir_extra_dirs());
}

/** @return array<string,mixed> */
function pe3_load_server_email_candidates(int $limit = 10): array
{
    $loader = new MaildirPreRideEmailLoaderV3();
    if (method_exists($loader, 'loadCandidates')) {
        return $loader->loadCandidates(pe3_maildir_extra_dirs(), $limit);
    }

    $latest = $loader->loadLatest(pe3_maildir_extra_dirs());
    return [
        'ok' => !empty($latest['ok']),
        'candidates' => !empty($latest['ok']) ? [[
            'email_text' => (string)($latest['email_text'] ?? ''),
            'source' => (string)($latest['source'] ?? ''),
            'source_mtime' => (string)($latest['source_mtime'] ?? ''),
            'source_mtime_epoch' => 0,
        ]] : [],
        'error' => (string)($latest['error'] ?? ''),
        'checked_dirs' => $latest['checked_dirs'] ?? [],
        'loader_version' => $latest['loader_version'] ?? 'legacy-v3-loader',
    ];
}

function pe3_el_date_from_iso(string $iso): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
        return $iso;
    }
    return $m[3] . '/' . $m[2] . '/' . $m[1];
}

/** @param array<string,string> $fields @return array{ok:bool,message:string,minutes_until:int|null,start_iso:string} */
function pe3_future_gate(array $fields): array
{
    $raw = trim((string)($fields['pickup_datetime_local'] ?? ''));
    if ($raw === '') {
        return ['ok' => false, 'message' => 'Pickup datetime is missing.', 'minutes_until' => null, 'start_iso' => ''];
    }
    try {
        $tz = new DateTimeZone('Europe/Athens');
        $pickup = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $tz);
        if (!$pickup) {
            $pickup = new DateTimeImmutable($raw, $tz);
        }
        $now = new DateTimeImmutable('now', $tz);
        $minutes = (int)floor(($pickup->getTimestamp() - $now->getTimestamp()) / 60);
        if ($minutes < PE3_MIN_FUTURE_MINUTES) {
            return [
                'ok' => false,
                'message' => 'Pickup is only ' . $minutes . ' minutes from now. V3 requires at least ' . PE3_MIN_FUTURE_MINUTES . ' minutes in the future.',
                'minutes_until' => $minutes,
                'start_iso' => $pickup->format(DateTimeInterface::ATOM),
            ];
        }
        return [
            'ok' => true,
            'message' => 'Pickup is ' . $minutes . ' minutes in the future.',
            'minutes_until' => $minutes,
            'start_iso' => $pickup->format(DateTimeInterface::ATOM),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Pickup datetime could not be validated: ' . $e->getMessage(), 'minutes_until' => null, 'start_iso' => ''];
    }
}

/** @param array<string,string> $fields @param array<string,mixed> $mapping @return array<string,mixed> */
function pe3_helper_payload(array $fields, array $mapping): array
{
    return [
        'toolVersion' => PE3_TOOL_VERSION,
        'source' => 'gov.cabnet.app pre-ride email tool v3 isolated',
        'savedAt' => date(DATE_ATOM),
        'lessor' => trim((string)($fields['operator'] ?? '')),
        'lessorId' => trim((string)($mapping['lessor_id'] ?? '')),
        'lessorSource' => trim((string)($mapping['lessor_source'] ?? '')),
        'driver' => trim((string)($fields['driver_name'] ?? '')),
        'driverId' => trim((string)($mapping['driver_id'] ?? '')),
        'vehicle' => trim((string)($fields['vehicle_plate'] ?? '')),
        'vehicleId' => trim((string)($mapping['vehicle_id'] ?? '')),
        'startingPointId' => trim((string)($mapping['starting_point_id'] ?? '')),
        'startingPointLabel' => trim((string)($mapping['starting_point_label'] ?? '')),
        'passengerName' => trim((string)($fields['customer_name'] ?? '')),
        'passengerPhone' => trim((string)($fields['customer_phone'] ?? '')),
        'pickupAddress' => trim((string)($fields['pickup_address'] ?? '')),
        'dropoffAddress' => trim((string)($fields['dropoff_address'] ?? '')),
        'pickupDateIso' => trim((string)($fields['pickup_date'] ?? '')),
        'pickupDateEl' => pe3_el_date_from_iso(trim((string)($fields['pickup_date'] ?? ''))),
        'pickupTime' => trim((string)($fields['pickup_time'] ?? '')),
        'pickupDateTime' => trim((string)($fields['pickup_datetime_local'] ?? '')),
        'endDateTime' => trim((string)($fields['end_datetime_local'] ?? '')),
        'priceText' => trim((string)($fields['estimated_price_text'] ?? '')),
        'priceAmount' => trim((string)($fields['estimated_price_amount'] ?? '')),
        'orderReference' => trim((string)($fields['order_reference'] ?? '')),
    ];
}


function pe3_norm_key_value($value): string
{
    $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

/** @param array<string,mixed> $fields @param array<string,mixed> $mapping @param array<string,mixed> $candidate */
function pe3_candidate_dedupe_key(array $fields, array $mapping, array $candidate): string
{
    $parts = [
        $fields['order_reference'] ?? '',
        $fields['pickup_datetime_local'] ?? '',
        $fields['customer_phone'] ?? '',
        $fields['vehicle_plate'] ?? '',
        $fields['driver_name'] ?? '',
        $fields['pickup_address'] ?? '',
        $fields['dropoff_address'] ?? '',
        $mapping['lessor_id'] ?? '',
    ];
    $base = implode('|', array_map('pe3_norm_key_value', $parts));
    if (trim(str_replace('|', '', $base)) === '') {
        $base = pe3_norm_key_value(($candidate['source'] ?? '') . '|' . ($candidate['source_mtime'] ?? ''));
    }
    return 'pe3_' . substr(hash('sha256', $base), 0, 24);
}

/** @return array<string,mixed> */
function pe3_analyze_candidate(array $candidate, int $index): array
{
    $row = [
        'index' => $index,
        'source' => (string)($candidate['source'] ?? ''),
        'source_mtime' => (string)($candidate['source_mtime'] ?? ''),
        'customer' => '',
        'driver' => '',
        'vehicle' => '',
        'pickup_datetime' => '',
        'minutes_until' => null,
        'parser_ok' => false,
        'mapping_ok' => false,
        'future_ok' => false,
        'ready' => false,
        'mapping_warnings' => [],
        'parser_warnings' => [],
        'missing_count' => null,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'order_reference' => '',
        'dedupe_key' => '',
        'queue_status' => 'blocked',
        'block_reasons' => [],
        'error' => '',
    ];

    try {
        $parser = new BoltPreRideEmailParserV3();
        $parsed = $parser->parse((string)($candidate['email_text'] ?? ''));
        $fields = is_array($parsed) ? ($parsed['fields'] ?? []) : [];
        $missing = is_array($parsed) ? ($parsed['missing_required'] ?? []) : [];
        $row['parser_ok'] = is_array($parsed) && empty($missing);
        $row['customer'] = (string)($fields['customer_name'] ?? '');
        $row['driver'] = (string)($fields['driver_name'] ?? '');
        $row['vehicle'] = (string)($fields['vehicle_plate'] ?? '');
        $row['pickup_datetime'] = (string)($fields['pickup_datetime_local'] ?? '');
        $row['order_reference'] = (string)($fields['order_reference'] ?? '');
        $row['missing_count'] = is_array($missing) ? count($missing) : null;
        $row['parser_warnings'] = is_array($parsed) ? ($parsed['warnings'] ?? []) : [];

        $lookupError = null;
        $mapping = pe3_lookup_edxeix_ids(is_array($fields) ? $fields : [], $lookupError);
        $future = pe3_future_gate(is_array($fields) ? $fields : []);
        $row['mapping_ok'] = !empty($mapping['ok']);
        $row['future_ok'] = !empty($future['ok']);
        $row['minutes_until'] = $future['minutes_until'] ?? null;
        $row['lessor_id'] = (string)($mapping['lessor_id'] ?? '');
        $row['driver_id'] = (string)($mapping['driver_id'] ?? '');
        $row['vehicle_id'] = (string)($mapping['vehicle_id'] ?? '');
        $row['starting_point_id'] = (string)($mapping['starting_point_id'] ?? '');
        $row['dedupe_key'] = pe3_candidate_dedupe_key(is_array($fields) ? $fields : [], $mapping, $candidate);
        $row['ready'] = !empty($row['parser_ok']) && !empty($row['mapping_ok']) && !empty($row['future_ok']);
        $row['queue_status'] = !empty($row['ready']) ? 'would_queue' : 'blocked';
        $row['mapping_warnings'] = $mapping['warnings'] ?? [];
        if ($lookupError) {
            $row['mapping_warnings'][] = $lookupError;
        }

        $blockReasons = [];
        if (empty($row['parser_ok'])) {
            $blockReasons[] = 'Parser is not complete' . (!empty($missing) ? ': ' . implode(', ', array_map('strval', $missing)) : '.');
        }
        if (empty($row['mapping_ok'])) {
            $blockReasons[] = 'EDXEIX IDs are not fully mapped.';
        }
        if (empty($row['future_ok'])) {
            $blockReasons[] = (string)($future['message'] ?? 'Future-time gate failed.');
        }
        $row['block_reasons'] = $blockReasons;
    } catch (Throwable $e) {
        $row['error'] = $e->getMessage();
        $row['block_reasons'] = ['Candidate analysis error: ' . $e->getMessage()];
    }

    return $row;
}

$manual = isset($_GET['manual']);
$watch = isset($_GET['watch']);
$jsonMode = (($_GET['format'] ?? '') === 'json');
$rawEmail = '';
$result = null;
$error = null;
$mailLoad = null;
$mailLoadError = null;
$dbLookupError = null;
$candidateLoad = null;
$candidateRows = [];
$queuePlan = [];
$queuePreviewJson = '{}';
$selectedCandidateIndex = null;
$autoSelectionReason = '';
$mapping = [
    'ok' => false,
    'lessor_id' => '',
    'driver_id' => '',
    'vehicle_id' => '',
    'starting_point_id' => '',
    'messages' => [],
    'warnings' => [],
];
$autoLoaded = false;

$shouldLoadCandidates = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'parse_pasted');
    if ($action === 'load_latest_server_email') {
        $shouldLoadCandidates = true;
    } else {
        $rawEmail = (string)($_POST['email_text'] ?? '');
    }
} elseif (!$manual) {
    $shouldLoadCandidates = true;
}

if ($shouldLoadCandidates) {
    $candidateLoad = pe3_load_server_email_candidates(10);
    if (!empty($candidateLoad['ok']) && !empty($candidateLoad['candidates']) && is_array($candidateLoad['candidates'])) {
        foreach ($candidateLoad['candidates'] as $idx => $candidate) {
            if (is_array($candidate)) {
                $candidateRows[] = pe3_analyze_candidate($candidate, (int)$idx);
            }
        }

        $requestedIndex = isset($_GET['candidate']) ? (int)$_GET['candidate'] : null;
        if ($requestedIndex !== null && isset($candidateLoad['candidates'][$requestedIndex])) {
            $selectedCandidateIndex = $requestedIndex;
            $autoSelectionReason = 'Operator selected candidate #' . ($requestedIndex + 1) . ' for inspection.';
        } else {
            foreach ($candidateRows as $row) {
                if (!empty($row['ready'])) {
                    $selectedCandidateIndex = (int)$row['index'];
                    $autoSelectionReason = 'V3 auto-selected the first future-ready Maildir candidate.';
                    break;
                }
            }
            if ($selectedCandidateIndex === null) {
                $selectedCandidateIndex = 0;
                $autoSelectionReason = 'No future-ready candidate was found; V3 is showing the latest candidate in preview-only mode.';
            }
        }

        $selected = $candidateLoad['candidates'][$selectedCandidateIndex] ?? null;
        if (is_array($selected)) {
            $rawEmail = (string)($selected['email_text'] ?? '');
            $mailLoad = [
                'ok' => true,
                'email_text' => $rawEmail,
                'source' => (string)($selected['source'] ?? ''),
                'source_mtime' => (string)($selected['source_mtime'] ?? ''),
                'checked_dirs' => $candidateLoad['checked_dirs'] ?? [],
                'loader_version' => $candidateLoad['loader_version'] ?? '',
                'selected_candidate_index' => $selectedCandidateIndex,
                'auto_selection_reason' => $autoSelectionReason,
            ];
            $autoLoaded = true;
        }
    } else {
        $mailLoadError = (string)($candidateLoad['error'] ?? 'Unable to load latest server email.');
    }
}

if (trim($rawEmail) !== '') {
    try {
        $parser = new BoltPreRideEmailParserV3();
        $result = $parser->parse($rawEmail);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $mailLoadError === null) {
    $error = 'No email text was provided.';
}

$fields = is_array($result) ? ($result['fields'] ?? []) : [];
$generated = is_array($result) ? ($result['generated'] ?? []) : [];
if (is_array($result)) {
    $mapping = pe3_lookup_edxeix_ids($fields, $dbLookupError);
}
$missing = is_array($result) ? ($result['missing_required'] ?? []) : [];
$warnings = is_array($result) ? ($result['warnings'] ?? []) : [];
$confidence = is_array($result) ? (string)($result['confidence'] ?? 'not parsed') : 'not parsed';
$futureGate = is_array($result) ? pe3_future_gate($fields) : ['ok' => false, 'message' => 'No parsed transfer yet.', 'minutes_until' => null, 'start_iso' => ''];
$ready = is_array($result) && empty($missing) && !empty($mapping['ok']) && !empty($futureGate['ok']);
$previewPayload = is_array($result) ? pe3_helper_payload($fields, $mapping) : [];
$payload = $ready ? $previewPayload : [];
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
$previewPayloadEnvelope = [
    'preview_only' => true,
    'not_saved_to_helper' => true,
    'reason' => $ready ? 'Active helper payload is ready; preview matches the active payload.' : 'One or more V3 gates are blocking helper activation.',
    'gates' => [
        'parser_ok' => is_array($result) && empty($missing),
        'mapping_ok' => !empty($mapping['ok']),
        'future_ok' => !empty($futureGate['ok']),
        'overall_ready' => $ready,
    ],
    'future_gate' => $futureGate,
    'payload_preview' => $previewPayload,
];
$previewPayloadJson = json_encode($previewPayloadEnvelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
$edxeixUrl = PE3_EDXEIX_CREATE_URL . (!empty($mapping['lessor_id']) ? ('?lessor=' . rawurlencode((string)$mapping['lessor_id'])) : '');

$queuePlan = [
    'tool_version' => PE3_TOOL_VERSION,
    'mode' => 'dry_run_queue_preview',
    'dry_run_only' => true,
    'db_write' => false,
    'edxeix_server_call' => false,
    'aade_call' => false,
    'ready_count' => 0,
    'blocked_count' => 0,
    'selected_candidate_index' => $selectedCandidateIndex,
    'rows' => [],
];
foreach ($candidateRows as $row) {
    $readyRow = !empty($row['ready']);
    if ($readyRow) {
        $queuePlan['ready_count']++;
    } else {
        $queuePlan['blocked_count']++;
    }
    $queuePlan['rows'][] = [
        'candidate_number' => ((int)($row['index'] ?? 0)) + 1,
        'selected' => ((int)($row['index'] ?? -1) === (int)$selectedCandidateIndex),
        'queue_status' => (string)($row['queue_status'] ?? ($readyRow ? 'would_queue' : 'blocked')),
        'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
        'source' => (string)($row['source'] ?? ''),
        'source_mtime' => (string)($row['source_mtime'] ?? ''),
        'customer' => (string)($row['customer'] ?? ''),
        'driver' => (string)($row['driver'] ?? ''),
        'vehicle' => (string)($row['vehicle'] ?? ''),
        'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
        'minutes_until' => $row['minutes_until'] ?? null,
        'lessor_id' => (string)($row['lessor_id'] ?? ''),
        'driver_id' => (string)($row['driver_id'] ?? ''),
        'vehicle_id' => (string)($row['vehicle_id'] ?? ''),
        'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
        'parser_ok' => !empty($row['parser_ok']),
        'mapping_ok' => !empty($row['mapping_ok']),
        'future_ok' => !empty($row['future_ok']),
        'block_reasons' => $row['block_reasons'] ?? [],
    ];
}
$queuePreviewJson = json_encode($queuePlan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';


if ($jsonMode) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => $ready,
        'tool_version' => PE3_TOOL_VERSION,
        'auto_loaded' => $autoLoaded,
        'mail_source' => is_array($mailLoad) ? ($mailLoad['source'] ?? '') : '',
        'selected_candidate_index' => $selectedCandidateIndex,
        'auto_selection_reason' => $autoSelectionReason,
        'candidate_rows' => $candidateRows,
        'queue_plan' => $queuePlan,
        'parser_ok' => is_array($result) && empty($missing),
        'mapping_ok' => !empty($mapping['ok']),
        'future_ok' => !empty($futureGate['ok']),
        'future_gate' => $futureGate,
        'fields' => $fields,
        'missing_required' => $missing,
        'warnings' => $warnings,
        'mapping' => [
            'ok' => !empty($mapping['ok']),
            'lessor_id' => $mapping['lessor_id'] ?? '',
            'driver_id' => $mapping['driver_id'] ?? '',
            'vehicle_id' => $mapping['vehicle_id'] ?? '',
            'starting_point_id' => $mapping['starting_point_id'] ?? '',
            'messages' => $mapping['messages'] ?? [],
            'warnings' => $mapping['warnings'] ?? [],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$sample = "Operator: Fleet Mykonos LUXLIMO IKE\nCustomer: Example Customer\nCustomer mobile: +306900000000\nDriver: Example Driver\nVehicle: ABC1234\nPickup: Mikonos 846 00, Greece\nDrop-off: Mykonos Airport, Greece\nStart time: 2026-05-10 18:10:00 EEST\nEstimated pick-up time: 2026-05-10 18:15:00 EEST\nEstimated end time: 2026-05-10 18:40:00 EEST\nEstimated price: 40.00 - 44.00 eur";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?php if ($watch && !$ready): ?><meta http-equiv="refresh" content="20"><?php endif; ?>
    <title>Bolt Pre-Ride Email Tool V3 | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff;--gold:#d4922d;--purple:#6d28d9}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{font-size:18px;margin:18px 0 10px}p{color:var(--muted);line-height:1.45}.safety{border-left:7px solid var(--green);background:#ecfdf3}.hero{border-left:7px solid var(--purple)}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1 / -1}label{font-weight:700;font-size:13px;color:#27385f}input,textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:11px 12px;font-size:15px;font-family:Arial,Helvetica,sans-serif;background:#fff;color:var(--ink)}textarea{min-height:240px;resize:vertical}.raw textarea{min-height:420px}.output textarea{min-height:150px;background:#fbfdff}.btn{display:inline-block;border:0;padding:11px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px;cursor:pointer}.btn.green{background:var(--green)}.btn.orange{background:var(--orange)}.btn.dark{background:var(--slate)}.btn.gold{background:var(--gold)}.btn.purple{background:var(--purple)}.btn.light{background:#eaf1ff;color:#1e40af}.btn:disabled,.btn.disabled{opacity:.48;cursor:not-allowed}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:86px}.metric strong{display:block;font-size:27px;line-height:1.08;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.warnline{color:#b45309}.badline{color:#991b1b}.goodline{color:#166534}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.form-note{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;color:#9a3412}.stepbox{background:#eef6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px;color:#1e3a8a}.okbox{background:#ecfdf3;border:1px solid #bbf7d0;border-radius:10px;padding:12px;color:#14532d}.badbox{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px;color:#991b1b}.code-box{font-family:Consolas,Menlo,Monaco,monospace;font-size:13px;line-height:1.35;min-height:280px;background:#0b1220;color:#dbeafe}.helper-status{display:inline-block;margin-left:8px;font-weight:700}.helper-status.ok{color:#166534}.helper-status.warn{color:#b45309}.helper-status.bad{color:#991b1b}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}.candidate-table{width:100%;border-collapse:collapse;font-size:13px;background:#fff}.candidate-table th,.candidate-table td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.candidate-table th{background:#f8fbff;color:#27385f;white-space:nowrap}.candidate-table tr.selected{background:#eef6ff}.candidate-table tr.ready{background:#ecfdf3}.candidate-table tr.blocked{background:#fff7ed}.mini{font-size:12px;color:var(--muted)}@media(max-width:980px){.two,.three,.field-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}.raw textarea{min-height:280px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/pre-ride-email-tool.php">Production Tool</a>
    <a href="/ops/pre-ride-email-toolv3.php">V3 Isolated Tool</a>
    <a href="/ops/pre-ride-email-toolv3.php?manual=1">V3 Manual</a>
    <a href="/ops/pre-ride-email-toolv3.php?watch=1">V3 Watch</a>
</nav>

<main class="wrap">
    <section class="card safety">
        <strong>V3 isolated route.</strong>
        This page is independent from <code>/ops/pre-ride-email-tool.php</code>. It uses <code>BoltMailV3</code> classes only, performs no DB writes, no server-side EDXEIX call, no AADE call, and creates no queue jobs.
    </section>

    <section class="card hero">
        <h1>Bolt Pre-Ride Email → V3 Automated Preflight</h1>
        <p>Default mode auto-loads the latest Maildir pre-ride email, parses it, resolves EDXEIX IDs with read-only lookup, and prepares a V3 helper payload only when all safety gates pass.</p>
        <div>
            <?= pe3_badge('V3 ISOLATED', 'purple') ?>
            <?= pe3_badge('NO PRODUCTION FILE CHANGE', 'good') ?>
            <?= pe3_badge('NO DB WRITE', 'good') ?>
            <?= pe3_badge('NO EDXEIX SERVER CALL', 'good') ?>
            <?= pe3_badge('NO AADE CALL', 'good') ?>
            <?= pe3_badge('DRY-RUN QUEUE PREVIEW', 'neutral') ?>
            <?= $watch && !$ready ? pe3_badge('WATCH MODE 20s', 'warn') : '' ?>
            <?= $watch && $ready ? pe3_badge('WATCH READY - REFRESH STOPPED', 'good') : '' ?>
        </div>
    </section>

    <?php if ($watch): ?>
    <section class="card <?= $ready ? 'okbox' : 'stepbox' ?>" id="watchPanel">
        <strong>V3 Watch mode:</strong>
        <?php if ($ready): ?>
            future-ready candidate found. Auto-refresh has stopped. Review the selected email, then use the V3 helper buttons only after visual verification.
        <?php else: ?>
            no future-ready candidate yet. This page refreshes every 20 seconds and will stop automatically when a future-ready candidate appears.
        <?php endif; ?>
        <div class="actions" style="margin-top:10px;">
            <button class="btn light" type="button" onclick="enableWatchNotifications()">Enable browser notification</button>
            <button class="btn dark" type="button" onclick="testWatchBeep()">Test beep</button>
            <span id="watchNotifyStatus" class="helper-status warn">Notifications optional</span>
        </div>
    </section>
    <?php endif; ?>

    <section class="two">
        <form class="card raw" method="post" action="/ops/pre-ride-email-toolv3.php<?= $watch ? '?watch=1' : '' ?>" autocomplete="off">
            <h2>1. Source email</h2>
            <p class="small">
                <?= $autoLoaded ? 'Latest server email was auto-loaded into this box.' : 'Paste manually, or click Load latest server email.' ?>
            </p>
            <textarea name="email_text" id="email_text" placeholder="Paste Bolt pre-ride email here..."><?= pe3_h($rawEmail) ?></textarea>
            <div class="actions">
                <button class="btn green" type="submit" name="action" value="parse_pasted">Parse pasted with V3</button>
                <button class="btn gold" type="submit" name="action" value="load_latest_server_email">Load latest server email with V3</button>
                <button class="btn light" type="button" onclick="loadSample()">Load safe sample</button>
                <button class="btn dark" type="button" onclick="clearInput()">Clear</button>
            </div>
            <?php if (!empty($candidateRows)): ?>
                <h3>Recent Maildir candidates</h3>
                <p class="small"><?= pe3_h($autoSelectionReason) ?></p>
                <div class="table-wrap">
                    <table class="candidate-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Source</th>
                                <th>Pickup</th>
                                <th>Transfer</th>
                                <th>Gates</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidateRows as $row):
                                $isSelected = ((int)$row['index'] === (int)$selectedCandidateIndex);
                                $rowClass = $isSelected ? 'selected' : (!empty($row['ready']) ? 'ready' : 'blocked');
                                $candidateUrl = '/ops/pre-ride-email-toolv3.php?candidate=' . rawurlencode((string)$row['index']) . ($watch ? '&watch=1' : '');
                            ?>
                                <tr class="<?= pe3_h($rowClass) ?>">
                                    <td><strong><?= pe3_h((string)((int)$row['index'] + 1)) ?></strong><?= $isSelected ? '<br>' . pe3_badge('selected', 'neutral') : '' ?></td>
                                    <td><?= pe3_h($row['source'] ?? '') ?><br><span class="mini"><?= pe3_h($row['source_mtime'] ?? '') ?></span></td>
                                    <td><?= pe3_h($row['pickup_datetime'] ?? '') ?><br><span class="mini"><?= is_numeric($row['minutes_until'] ?? null) ? pe3_h((string)$row['minutes_until']) . ' min' : '-' ?></span></td>
                                    <td><?= pe3_h($row['customer'] ?? '') ?><br><span class="mini"><?= pe3_h(($row['driver'] ?? '') . ' / ' . ($row['vehicle'] ?? '')) ?></span></td>
                                    <td>
                                        <?= !empty($row['parser_ok']) ? pe3_badge('parser', 'good') : pe3_badge('parser', 'warn') ?>
                                        <?= !empty($row['mapping_ok']) ? pe3_badge('ids', 'good') : pe3_badge('ids', 'warn') ?>
                                        <?= !empty($row['future_ok']) ? pe3_badge('future', 'good') : pe3_badge('past/soon', 'bad') ?>
                                        <?= !empty($row['ready']) ? pe3_badge('ready', 'good') : pe3_badge('blocked', 'bad') ?>
                                    </td>
                                    <td><a class="btn light" href="<?= pe3_h($candidateUrl) ?>">Inspect</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <h3>V3 dry-run queue preview</h3>
                <p class="small"><strong>DRY RUN ONLY — NO DB WRITE.</strong> This previews which recent emails would become queue records later. It does not insert into any queue table and does not call EDXEIX.</p>
                <div class="actions">
                    <button class="btn light" type="button" onclick="copyQueuePreview()">Copy queue preview JSON</button>
                    <span id="queuePreviewStatus" class="helper-status warn">Dry-run preview only</span>
                </div>
                <div class="table-wrap" style="margin-top:10px;">
                    <table class="candidate-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Queue status</th>
                                <th>Dedupe key</th>
                                <th>IDs</th>
                                <th>Block reasons</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidateRows as $row): ?>
                                <tr class="<?= !empty($row['ready']) ? 'ready' : 'blocked' ?>">
                                    <td><strong><?= pe3_h((string)((int)$row['index'] + 1)) ?></strong></td>
                                    <td><?= !empty($row['ready']) ? pe3_badge('would queue', 'good') : pe3_badge('blocked', 'bad') ?></td>
                                    <td><code><?= pe3_h($row['dedupe_key'] ?? '') ?></code></td>
                                    <td class="mini">
                                        Lessor: <?= pe3_h($row['lessor_id'] ?? '') ?><br>
                                        Driver: <?= pe3_h($row['driver_id'] ?? '') ?><br>
                                        Vehicle: <?= pe3_h($row['vehicle_id'] ?? '') ?><br>
                                        Start: <?= pe3_h($row['starting_point_id'] ?? '') ?>
                                    </td>
                                    <td class="mini">
                                        <?php if (!empty($row['block_reasons'])): ?>
                                            <?php foreach ((array)$row['block_reasons'] as $reason): ?>
                                                <div>• <?= pe3_h($reason) ?></div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            Ready for future V3 queue insertion after explicit approval.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <textarea id="queuePreviewJson" class="code-box" style="min-height:180px;margin-top:10px;" readonly><?= pe3_h($queuePreviewJson) ?></textarea>
            <?php endif; ?>
        </form>

        <section class="card">
            <h2>2. V3 preflight status</h2>
            <?php if ($error): ?>
                <p class="badline"><strong>Error:</strong> <?= pe3_h($error) ?></p>
            <?php elseif ($result === null): ?>
                <p>No email parsed yet.</p>
                <?php if ($mailLoadError): ?><p class="warnline"><strong>Mail loader:</strong> <?= pe3_h($mailLoadError) ?></p><?php endif; ?>
            <?php else: ?>
                <div class="three">
                    <div class="metric"><strong><?= pe3_h(strtoupper($confidence)) ?></strong><span>Parser confidence</span></div>
                    <div class="metric"><strong><?= pe3_h((string)count($missing)) ?></strong><span>Missing fields</span></div>
                    <div class="metric"><strong><?= pe3_h((string)($futureGate['minutes_until'] ?? '-')) ?></strong><span>Minutes until pickup</span></div>
                </div>
                <p>
                    Parser: <?= empty($missing) ? pe3_badge('OK', 'good') : pe3_badge('CHECK', 'warn') ?>
                    Mapping: <?= !empty($mapping['ok']) ? pe3_badge('IDS READY', 'good') : pe3_badge('CHECK IDS', 'warn') ?>
                    Future gate: <?= !empty($futureGate['ok']) ? pe3_badge('FUTURE OK', 'good') : pe3_badge('BLOCKED', 'bad') ?>
                    Overall: <?= $ready ? pe3_badge('READY FOR V3 HELPER', 'good') : pe3_badge('NOT READY', 'bad') ?>
                </p>
                <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                    <p class="goodline"><strong>Server email loaded:</strong> <?= pe3_h($mailLoad['source'] ?? '') ?> <?= !empty($mailLoad['source_mtime']) ? '(' . pe3_h($mailLoad['source_mtime']) . ')' : '' ?></p>
                    <?php if (!empty($mailLoad['auto_selection_reason'])): ?><p class="small"><strong>Selection:</strong> <?= pe3_h($mailLoad['auto_selection_reason']) ?></p><?php endif; ?>
                <?php elseif ($mailLoadError): ?>
                    <p class="warnline"><strong>Server email loader:</strong> <?= pe3_h($mailLoadError) ?></p>
                <?php endif; ?>
                <?php if ($dbLookupError): ?><p class="warnline"><strong>DB ID lookup:</strong> <?= pe3_h($dbLookupError) ?></p><?php endif; ?>
                <p class="<?= !empty($futureGate['ok']) ? 'goodline' : 'badline' ?>"><strong>Future gate:</strong> <?= pe3_h($futureGate['message'] ?? '') ?></p>
                <?php if (!empty($missing)): ?>
                    <h3>Missing required fields</h3>
                    <ul class="list"><?php foreach ($missing as $item): ?><li class="warnline"><?= pe3_h($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($warnings)): ?>
                    <h3>Parser warnings</h3>
                    <ul class="list"><?php foreach ($warnings as $item): ?><li class="warnline"><?= pe3_h($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <h3>DB lookup</h3>
                <div class="stepbox">
                    <?php foreach (($mapping['messages'] ?? []) as $msg): ?><div class="goodline">✓ <?= pe3_h($msg) ?></div><?php endforeach; ?>
                    <?php foreach (($mapping['warnings'] ?? []) as $msg): ?><div class="warnline">⚠ <?= pe3_h($msg) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>

    <?php if (is_array($result)): ?>
    <section class="card">
        <h2>3. Extracted transfer fields</h2>
        <div class="field-grid">
            <?php
            $showFields = [
                'operator' => 'Operator / Fleet',
                'customer_name' => 'Customer',
                'customer_phone' => 'Customer mobile',
                'driver_name' => 'Driver',
                'vehicle_plate' => 'Vehicle',
                'pickup_address' => 'Pickup',
                'dropoff_address' => 'Drop-off',
                'pickup_datetime_local' => 'Pickup datetime',
                'end_datetime_local' => 'Estimated end',
                'estimated_price_text' => 'Estimated price text',
                'estimated_price_amount' => 'Price amount used by V3',
                'order_reference' => 'Order reference',
            ];
            foreach ($showFields as $key => $label): ?>
                <div class="field <?= in_array($key, ['pickup_address','dropoff_address'], true) ? 'full' : '' ?>">
                    <label><?= pe3_h($label) ?></label>
                    <input readonly value="<?= pe3_h($fields[$key] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>4. V3 helper payload</h2>
        <?php if ($ready): ?>
            <div class="okbox"><strong>Ready.</strong> V3 can save this payload to the optional V3 Firefox helper. The helper is fill-only and does not POST to EDXEIX.</div>
            <div class="actions">
                <button class="btn purple" type="button" onclick="saveV3PayloadAndOpen()">Save V3 payload + open EDXEIX</button>
                <button class="btn green" type="button" onclick="saveV3PayloadOnly()">Save V3 payload only</button>
                <a class="btn orange" href="<?= pe3_h($edxeixUrl) ?>" target="_blank" rel="noopener">Open EDXEIX only</a>
                <button class="btn light" type="button" onclick="copyPayload()">Copy payload JSON</button>
                <span id="helperStatus" class="helper-status warn">V3 helper not confirmed yet</span>
            </div>
        <?php else: ?>
            <div class="badbox"><strong>Blocked.</strong> V3 helper payload is not enabled until parser, ID lookup, and future-time gates pass.</div>
            <h3>Diagnostic payload preview</h3>
            <p class="small"><strong>PREVIEW ONLY — NOT SAVED TO HELPER.</strong> This shows the exact data V3 would send after all safety gates pass. Use it to debug IDs and parsed fields for past or blocked rides without enabling any EDXEIX action.</p>
            <div class="actions">
                <button class="btn light" type="button" onclick="copyPreviewPayload()">Copy preview JSON</button>
                <span id="previewStatus" class="helper-status warn">Preview only</span>
            </div>
            <textarea id="previewPayloadJson" class="code-box" readonly><?= pe3_h($previewPayloadJson) ?></textarea>
        <?php endif; ?>
        <h3><?= $ready ? 'Active helper payload' : 'Active helper payload' ?></h3>
        <textarea id="payloadJson" class="code-box" readonly><?= pe3_h($payloadJson) ?></textarea>
    </section>

    <section class="two">
        <section class="card output">
            <h2>Dispatch summary</h2>
            <textarea readonly><?= pe3_h($generated['dispatch_summary'] ?? '') ?></textarea>
        </section>
        <section class="card output">
            <h2>CSV row</h2>
            <textarea readonly><?= pe3_h(($generated['csv_header'] ?? '') . "\n" . ($generated['csv_row'] ?? '')) ?></textarea>
        </section>
    </section>
    <?php endif; ?>

    <section class="card form-note">
        <strong>Isolation guarantee:</strong> this package must not replace, include, or modify <code>/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php</code>. Use the production page as before while V3 is tested at <code>/ops/pre-ride-email-toolv3.php</code>.
    </section>
</main>

<script>
const SAFE_SAMPLE = <?= json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const V3_PAYLOAD = <?= $payloadJson ?>;
const EDXEIX_URL = <?= json_encode($edxeixUrl, JSON_UNESCAPED_SLASHES) ?>;
const V3_WATCH_MODE = <?= $watch ? 'true' : 'false' ?>;
const V3_READY = <?= $ready ? 'true' : 'false' ?>;
const V3_WATCH_MESSAGE = <?= json_encode($ready ? 'V3 future-ready Bolt pre-ride email found. Review before EDXEIX fill.' : 'V3 watch mode active. No future-ready candidate yet.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function watchStatus(text, cls){ const el=document.getElementById('watchNotifyStatus'); if(!el){return;} el.textContent=text; el.className='helper-status '+cls; }
function playWatchBeep(){
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) { return false; }
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.55);
        osc.connect(gain); gain.connect(ctx.destination);
        osc.start(); osc.stop(ctx.currentTime + 0.6);
        return true;
    } catch(e) { return false; }
}
function testWatchBeep(){ if (playWatchBeep()) { watchStatus('Beep tested', 'ok'); } else { watchStatus('Browser blocked sound', 'warn'); } }
async function enableWatchNotifications(){
    try {
        localStorage.setItem('govCabnetV3WatchNotify', '1');
        if (!('Notification' in window)) { watchStatus('Browser notifications unavailable; visual alert only', 'warn'); return; }
        if (Notification.permission === 'default') { await Notification.requestPermission(); }
        if (Notification.permission === 'granted') { watchStatus('Notifications enabled', 'ok'); }
        else { watchStatus('Notifications not allowed; visual alert only', 'warn'); }
    } catch(e) { watchStatus('Notification setup failed', 'bad'); }
}
function fireWatchReadyAlert(){
    if (!V3_WATCH_MODE || !V3_READY) { return; }
    document.title = 'READY - V3 Pre-Ride Email';
    const key = 'govCabnetV3WatchReadyShown:' + (V3_PAYLOAD && V3_PAYLOAD.pickupDateTime ? V3_PAYLOAD.pickupDateTime : location.href);
    if (sessionStorage.getItem(key)) { return; }
    sessionStorage.setItem(key, '1');
    const panel = document.getElementById('watchPanel');
    if (panel) { panel.scrollIntoView({ behavior:'smooth', block:'center' }); }
    playWatchBeep();
    try {
        if (localStorage.getItem('govCabnetV3WatchNotify') === '1' && 'Notification' in window && Notification.permission === 'granted') {
            new Notification('Gov Cabnet V3 ready', { body: V3_WATCH_MESSAGE });
        }
    } catch(e) {}
    watchStatus('Ready alert fired', 'ok');
}
function loadSample(){ document.getElementById('email_text').value = SAFE_SAMPLE; }
function clearInput(){ document.getElementById('email_text').value = ''; }
function setHelperStatus(text, cls){ const el=document.getElementById('helperStatus'); if(!el){return;} el.textContent=text; el.className='helper-status '+cls; }
async function copyPayload(){
    const text = document.getElementById('payloadJson').value;
    try { await navigator.clipboard.writeText(text); setHelperStatus('Payload copied', 'ok'); }
    catch(e){ setHelperStatus('Copy failed; select JSON manually', 'bad'); }
}
async function copyQueuePreview(){
    const box = document.getElementById('queuePreviewJson');
    const status = document.getElementById('queuePreviewStatus');
    if (!box) { return; }
    try { await navigator.clipboard.writeText(box.value); if(status){ status.textContent='Queue preview copied'; status.className='helper-status ok'; } }
    catch(e){ if(status){ status.textContent='Copy failed; select JSON manually'; status.className='helper-status bad'; } }
}
async function copyPreviewPayload(){
    const box = document.getElementById('previewPayloadJson');
    const status = document.getElementById('previewStatus');
    if (!box) { return; }
    try { await navigator.clipboard.writeText(box.value); if(status){ status.textContent='Preview copied'; status.className='helper-status ok'; } }
    catch(e){ if(status){ status.textContent='Copy failed; select JSON manually'; status.className='helper-status bad'; } }
}
function saveV3PayloadOnly(){
    if (!V3_PAYLOAD || !V3_PAYLOAD.lessorId) { setHelperStatus('Payload is not ready', 'bad'); return; }
    window.postMessage({ type:'GOV_CABNET_EDXEIX_PAYLOAD_V3', payload: V3_PAYLOAD }, '*');
    setHelperStatus('Sent to V3 helper; waiting confirmation...', 'warn');
}
function saveV3PayloadAndOpen(){
    saveV3PayloadOnly();
    setTimeout(function(){ window.open(EDXEIX_URL, '_blank', 'noopener'); }, 650);
}
fireWatchReadyAlert();
window.addEventListener('message', function(event){
    if (event.source !== window) { return; }
    const msg = event.data || {};
    if (msg.type !== 'GOV_CABNET_EDXEIX_PAYLOAD_V3_SAVED') { return; }
    if (msg.ok) { setHelperStatus('V3 payload saved to helper at '+(msg.savedAt || ''), 'ok'); }
    else { setHelperStatus('V3 helper save failed: '+(msg.error || 'unknown'), 'bad'); }
});
</script>
</body>
</html>
