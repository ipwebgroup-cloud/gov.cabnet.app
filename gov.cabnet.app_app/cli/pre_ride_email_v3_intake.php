<?php
/**
 * gov.cabnet.app — V3 pre-ride email queue intake CLI.
 *
 * Purpose:
 * - Scan recent Bolt pre-ride Maildir emails.
 * - Parse and preflight them with isolated BoltMailV3 classes.
 * - Default to dry-run preview.
 * - With --commit, insert only future-ready candidates into V3-only queue tables.
 *
 * Safety:
 * - No production pre-ride-email-tool.php dependency.
 * - No production submission_jobs/submission_attempts writes.
 * - No EDXEIX server-side calls.
 * - No AADE calls.
 * - No email deletion/move/mark-read.
 * - Uses INSERT IGNORE with deterministic V3 dedupe keys.
 */

declare(strict_types=1);

const PE3_CLI_VERSION = 'v3.0.9-fast-queue-intake';
const PE3_DEFAULT_LIMIT = 20;
const PE3_DEFAULT_MIN_FUTURE_MINUTES = 1;

$appRoot = dirname(__DIR__);
$bootstrapFile = $appRoot . '/src/bootstrap.php';
$parserFile = $appRoot . '/src/BoltMailV3/BoltPreRideEmailParserV3.php';
$lookupFile = $appRoot . '/src/BoltMailV3/EdxeixMappingLookupV3.php';
$mailLoaderFile = $appRoot . '/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php';

foreach ([$bootstrapFile, $parserFile, $lookupFile, $mailLoaderFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing required file: {$file}\n");
        exit(2);
    }
    require_once $file;
}

use Bridge\BoltMailV3\BoltPreRideEmailParserV3;
use Bridge\BoltMailV3\EdxeixMappingLookupV3;
use Bridge\BoltMailV3\MaildirPreRideEmailLoaderV3;

function pe3_cli_default_min_future_minutes(): int
{
    $env = getenv('GOV_CABNET_V3_MIN_FUTURE_MINUTES');
    if (is_string($env) && preg_match('/^\d+$/', trim($env))) {
        return max(1, min(1440, (int)trim($env)));
    }
    return PE3_DEFAULT_MIN_FUTURE_MINUTES;
}

/** @return array<string,mixed> */
function pe3_cli_options(array $argv): array
{
    $opts = [
        'commit' => false,
        'json' => false,
        'limit' => PE3_DEFAULT_LIMIT,
        'min_future_minutes' => pe3_cli_default_min_future_minutes(),
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--commit') {
            $opts['commit'] = true;
        } elseif ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
            $opts['limit'] = max(1, min(100, (int)$m[1]));
        } elseif (preg_match('/^--min-future-minutes=(\d+)$/', $arg, $m)) {
            $opts['min_future_minutes'] = max(1, min(1440, (int)$m[1]));
        } else {
            fwrite(STDERR, "Unknown option: {$arg}\n");
            $opts['help'] = true;
        }
    }

    return $opts;
}

function pe3_cli_help(): string
{
    return <<<TEXT
V3 pre-ride email queue intake CLI

Usage:
  php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php [options]

Options:
  --limit=N                 Number of recent Maildir candidates to scan. Default: 20, max: 100.
  --min-future-minutes=N    Queue intake future window. Default: 1 minute. Env override: GOV_CABNET_V3_MIN_FUTURE_MINUTES.
  --json                    Output machine-readable JSON.
  --commit                  Insert only future-ready candidates into V3-only queue tables.
  --help                    Show this help.

Default mode is DRY RUN. It does not write to the database.
--commit writes only to pre_ride_email_v3_queue and pre_ride_email_v3_queue_events.
It never writes to submission_jobs/submission_attempts and never calls EDXEIX or AADE.

TEXT;
}

/** @return array<string,mixed> */
function pe3_cli_context(): array
{
    $ctx = require dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap did not return a usable DB context.');
    }
    return $ctx;
}

/** @return mysqli */
function pe3_cli_db(): mysqli
{
    $ctx = pe3_cli_context();
    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('DB connection is not mysqli.');
    }
    return $db;
}

/** @return array<int,string> */
function pe3_cli_maildir_extra_dirs(): array
{
    $extraDirs = [];
    try {
        $ctx = pe3_cli_context();
        if (isset($ctx['config']) && method_exists($ctx['config'], 'get')) {
            foreach (['mail.pre_ride_maildir_v3', 'mail.pre_ride_maildir'] as $key) {
                $single = $ctx['config']->get($key);
                if (is_string($single) && trim($single) !== '') {
                    $extraDirs[] = trim($single);
                }
            }
            foreach (['mail.pre_ride_maildirs_v3', 'mail.pre_ride_maildirs'] as $key) {
                $many = $ctx['config']->get($key, []);
                if (is_array($many)) {
                    foreach ($many as $dir) {
                        if (is_string($dir) && trim($dir) !== '') {
                            $extraDirs[] = trim($dir);
                        }
                    }
                }
            }
        }
    } catch (Throwable) {
        // Loader still has safe default Maildir paths.
    }
    return array_values(array_unique($extraDirs));
}

/** @return array<string,mixed> */
function pe3_cli_load_candidates(int $limit): array
{
    $loader = new MaildirPreRideEmailLoaderV3();
    if (method_exists($loader, 'loadCandidates')) {
        return $loader->loadCandidates(pe3_cli_maildir_extra_dirs(), $limit);
    }

    $latest = $loader->loadLatest(pe3_cli_maildir_extra_dirs());
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

/** @param array<string,mixed> $fields @return array<string,mixed> */
function pe3_cli_lookup(mysqli $db, array $fields): array
{
    $lookup = new EdxeixMappingLookupV3($db);
    return $lookup->lookup($fields);
}

/** @param array<string,mixed> $fields @return array{ok:bool,message:string,minutes_until:int|null,start_iso:string} */
function pe3_cli_future_gate(array $fields, int $minFutureMinutes): array
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
        if ($minutes < $minFutureMinutes) {
            return [
                'ok' => false,
                'message' => 'Pickup is only ' . $minutes . ' minutes from now. V3 fast intake requires at least ' . $minFutureMinutes . ' minute(s) in the future.',
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
        return ['ok' => false, 'message' => 'Pickup datetime could not be parsed: ' . $e->getMessage(), 'minutes_until' => null, 'start_iso' => ''];
    }
}

function pe3_cli_norm_key_value($value): string
{
    $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

/** @param array<string,mixed> $fields @param array<string,mixed> $mapping @param array<string,mixed> $candidate */
function pe3_cli_dedupe_key(array $fields, array $mapping, array $candidate): string
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
    $base = implode('|', array_map('pe3_cli_norm_key_value', $parts));
    if (trim(str_replace('|', '', $base)) === '') {
        $base = pe3_cli_norm_key_value(($candidate['source'] ?? '') . '|' . ($candidate['source_mtime'] ?? ''));
    }
    return 'pe3_' . substr(hash('sha256', $base), 0, 24);
}

function pe3_cli_el_date_from_iso(string $iso): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
        return $iso;
    }
    return $m[3] . '/' . $m[2] . '/' . $m[1];
}

/** @param array<string,mixed> $fields @param array<string,mixed> $mapping @return array<string,mixed> */
function pe3_cli_helper_payload(array $fields, array $mapping): array
{
    $pickupDate = trim((string)($fields['pickup_date'] ?? ''));
    return [
        'v3' => true,
        'source' => 'gov.cabnet.app pre-ride email tool v3 cli intake',
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
        'pickupDateIso' => $pickupDate,
        'pickupDateEl' => pe3_cli_el_date_from_iso($pickupDate),
        'pickupTime' => trim((string)($fields['pickup_time'] ?? '')),
        'pickupDateTime' => trim((string)($fields['pickup_datetime_local'] ?? '')),
        'endDateTime' => trim((string)($fields['end_datetime_local'] ?? '')),
        'priceText' => trim((string)($fields['estimated_price_text'] ?? '')),
        'priceAmount' => trim((string)($fields['estimated_price_amount'] ?? '')),
        'orderReference' => trim((string)($fields['order_reference'] ?? '')),
    ];
}

function pe3_cli_nullable_string($value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function pe3_cli_decimal_or_null($value): ?string
{
    $value = trim((string)$value);
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    return number_format((float)$value, 2, '.', '');
}

function pe3_cli_email_preview(string $email): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $email);
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    $text = trim($text);
    return function_exists('mb_substr') ? mb_substr($text, 0, 6000, 'UTF-8') : substr($text, 0, 6000);
}

/** @param array<int,mixed> $values */
function pe3_cli_bind_values(mysqli_stmt $stmt, array &$values): bool
{
    $types = str_repeat('s', count($values));
    $refs = [];
    $refs[] = &$types;
    foreach ($values as $i => &$value) {
        $refs[] = &$value;
    }
    return $stmt->bind_param(...$refs);
}

/** @return array<string,bool> */
function pe3_cli_schema_tables(mysqli $db): array
{
    $tables = [
        'pre_ride_email_v3_queue' => false,
        'pre_ride_email_v3_queue_events' => false,
    ];
    $res = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('pre_ride_email_v3_queue','pre_ride_email_v3_queue_events')");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $name = (string)($row['TABLE_NAME'] ?? '');
            if (array_key_exists($name, $tables)) {
                $tables[$name] = true;
            }
        }
    }
    return $tables;
}

/** @return bool */
function pe3_cli_schema_ok(mysqli $db): bool
{
    return !in_array(false, pe3_cli_schema_tables($db), true);
}

/** @param array<string,mixed> $candidate @return array<string,mixed> */
function pe3_cli_analyze_candidate(mysqli $db, array $candidate, int $index, int $minFutureMinutes): array
{
    $parser = new BoltPreRideEmailParserV3();
    $emailText = (string)($candidate['email_text'] ?? '');
    $parsed = $parser->parse($emailText);
    $fields = is_array($parsed) ? (array)($parsed['fields'] ?? []) : [];
    $missing = is_array($parsed) ? (array)($parsed['missing_required'] ?? []) : ['parse_failed'];
    $parserOk = is_array($parsed) && empty($missing);

    $mapping = pe3_cli_lookup($db, $fields);
    $mappingOk = !empty($mapping['ok']);
    $future = pe3_cli_future_gate($fields, $minFutureMinutes);
    $futureOk = !empty($future['ok']);

    $blockReasons = [];
    if (!$parserOk) {
        $blockReasons[] = 'Parser is not complete' . (!empty($missing) ? ': ' . implode(', ', array_map('strval', $missing)) : '.');
    }
    if (!$mappingOk) {
        $blockReasons[] = 'EDXEIX IDs are not fully mapped.';
    }
    if (!$futureOk) {
        $blockReasons[] = (string)($future['message'] ?? 'Future-time gate failed.');
    }

    $dedupeKey = pe3_cli_dedupe_key($fields, $mapping, $candidate);
    $payload = pe3_cli_helper_payload($fields, $mapping);
    $ready = $parserOk && $mappingOk && $futureOk;

    return [
        'index' => $index,
        'candidate_number' => $index + 1,
        'source_mailbox' => (string)($candidate['source'] ?? ''),
        'source_mtime' => (string)($candidate['source_mtime'] ?? ''),
        'source_hash' => hash('sha256', (string)($candidate['source'] ?? '') . '|' . (string)($candidate['source_mtime'] ?? '')),
        'email_hash' => hash('sha256', $emailText),
        'dedupe_key' => $dedupeKey,
        'ready' => $ready,
        'queue_status' => $ready ? 'queued' : 'blocked',
        'parser_ok' => $parserOk,
        'mapping_ok' => $mappingOk,
        'future_ok' => $futureOk,
        'future' => $future,
        'block_reasons' => $blockReasons,
        'parsed' => $parsed,
        'fields' => $fields,
        'mapping' => $mapping,
        'payload' => $payload,
        'raw_email_preview' => pe3_cli_email_preview($emailText),
        'summary' => [
            'customer' => (string)($fields['customer_name'] ?? ''),
            'driver' => (string)($fields['driver_name'] ?? ''),
            'vehicle' => (string)($fields['vehicle_plate'] ?? ''),
            'pickup_datetime' => (string)($fields['pickup_datetime_local'] ?? ''),
            'minutes_until' => $future['minutes_until'] ?? null,
            'lessor_id' => (string)($mapping['lessor_id'] ?? ''),
            'driver_id' => (string)($mapping['driver_id'] ?? ''),
            'vehicle_id' => (string)($mapping['vehicle_id'] ?? ''),
            'starting_point_id' => (string)($mapping['starting_point_id'] ?? ''),
        ],
    ];
}

/** @return array<string,mixed> */
function pe3_cli_insert_event(mysqli $db, ?string $queueId, string $dedupeKey, string $type, string $status, string $message, array $context = []): array
{
    $sql = "INSERT INTO pre_ride_email_v3_queue_events (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, 'v3_cli')";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Event prepare failed: ' . $db->error];
    }
    $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    $values = [$queueId, $dedupeKey, $type, $status, $message, $contextJson];
    if (!pe3_cli_bind_values($stmt, $values)) {
        return ['ok' => false, 'error' => 'Event bind failed: ' . $stmt->error];
    }
    if (!$stmt->execute()) {
        return ['ok' => false, 'error' => 'Event execute failed: ' . $stmt->error];
    }
    return ['ok' => true, 'event_id' => (string)$db->insert_id];
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function pe3_cli_insert_queue_row(mysqli $db, array $row): array
{
    if (empty($row['ready'])) {
        return ['status' => 'blocked_skipped', 'dedupe_key' => $row['dedupe_key'] ?? '', 'block_reasons' => $row['block_reasons'] ?? []];
    }

    $fields = (array)($row['fields'] ?? []);
    $mapping = (array)($row['mapping'] ?? []);
    $future = (array)($row['future'] ?? []);
    $payload = (array)($row['payload'] ?? []);

    $record = [
        'dedupe_key' => (string)$row['dedupe_key'],
        'source_mailbox' => pe3_cli_nullable_string($row['source_mailbox'] ?? ''),
        'source_mtime' => pe3_cli_nullable_string($row['source_mtime'] ?? ''),
        'source_hash' => (string)$row['source_hash'],
        'email_hash' => (string)$row['email_hash'],
        'order_reference' => pe3_cli_nullable_string($fields['order_reference'] ?? ''),
        'queue_status' => 'queued',
        'parser_ok' => '1',
        'mapping_ok' => '1',
        'future_ok' => '1',
        'lessor_id' => pe3_cli_nullable_string($mapping['lessor_id'] ?? ''),
        'lessor_source' => pe3_cli_nullable_string($mapping['lessor_source'] ?? ''),
        'driver_id' => pe3_cli_nullable_string($mapping['driver_id'] ?? ''),
        'vehicle_id' => pe3_cli_nullable_string($mapping['vehicle_id'] ?? ''),
        'starting_point_id' => pe3_cli_nullable_string($mapping['starting_point_id'] ?? ''),
        'customer_name' => pe3_cli_nullable_string($fields['customer_name'] ?? ''),
        'customer_phone' => pe3_cli_nullable_string($fields['customer_phone'] ?? ''),
        'driver_name' => pe3_cli_nullable_string($fields['driver_name'] ?? ''),
        'vehicle_plate' => pe3_cli_nullable_string($fields['vehicle_plate'] ?? ''),
        'pickup_datetime' => pe3_cli_nullable_string($fields['pickup_datetime_local'] ?? ''),
        'estimated_end_datetime' => pe3_cli_nullable_string($fields['end_datetime_local'] ?? ''),
        'minutes_until_at_intake' => isset($future['minutes_until']) && $future['minutes_until'] !== null ? (string)$future['minutes_until'] : null,
        'pickup_address' => pe3_cli_nullable_string($fields['pickup_address'] ?? ''),
        'dropoff_address' => pe3_cli_nullable_string($fields['dropoff_address'] ?? ''),
        'price_text' => pe3_cli_nullable_string($fields['estimated_price_text'] ?? ''),
        'price_amount' => pe3_cli_decimal_or_null($fields['estimated_price_amount'] ?? ''),
        'block_reasons_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'parsed_fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'raw_email_preview' => (string)($row['raw_email_preview'] ?? ''),
        'operator_note' => 'Inserted by V3 CLI queue intake ' . PE3_CLI_VERSION,
        'queued_at' => date('Y-m-d H:i:s'),
    ];

    $columns = array_keys($record);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = 'INSERT IGNORE INTO pre_ride_email_v3_queue (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return ['status' => 'error', 'dedupe_key' => $row['dedupe_key'] ?? '', 'error' => 'Queue insert prepare failed: ' . $db->error];
    }

    $values = array_values($record);
    if (!pe3_cli_bind_values($stmt, $values)) {
        return ['status' => 'error', 'dedupe_key' => $row['dedupe_key'] ?? '', 'error' => 'Queue insert bind failed: ' . $stmt->error];
    }
    if (!$stmt->execute()) {
        return ['status' => 'error', 'dedupe_key' => $row['dedupe_key'] ?? '', 'error' => 'Queue insert execute failed: ' . $stmt->error];
    }

    if ($stmt->affected_rows === 1) {
        $queueId = (string)$db->insert_id;
        pe3_cli_insert_event($db, $queueId, (string)$record['dedupe_key'], 'cli_queue_intake', 'queued', 'Candidate inserted by V3 CLI queue intake.', [
            'version' => PE3_CLI_VERSION,
            'source_mailbox' => $record['source_mailbox'],
            'pickup_datetime' => $record['pickup_datetime'],
        ]);
        return [
            'status' => 'inserted',
            'queue_id' => $queueId,
            'dedupe_key' => (string)$record['dedupe_key'],
            'pickup_datetime' => $record['pickup_datetime'],
            'customer_name' => $record['customer_name'],
        ];
    }

    $queueId = null;
    $sel = $db->prepare('SELECT id FROM pre_ride_email_v3_queue WHERE dedupe_key = ? LIMIT 1');
    if ($sel) {
        $dedupeKey = (string)$record['dedupe_key'];
        $sel->bind_param('s', $dedupeKey);
        $sel->execute();
        $existing = $sel->get_result()->fetch_assoc();
        if (is_array($existing) && isset($existing['id'])) {
            $queueId = (string)$existing['id'];
        }
    }

    return ['status' => 'duplicate_existing', 'queue_id' => $queueId, 'dedupe_key' => (string)$record['dedupe_key']];
}

/** @return array<string,mixed> */
function pe3_cli_run(array $argv): array
{
    $opts = pe3_cli_options($argv);
    if (!empty($opts['help'])) {
        return ['help' => true, 'text' => pe3_cli_help()];
    }

    $db = pe3_cli_db();
    $schemaTables = pe3_cli_schema_tables($db);
    $schemaOk = !in_array(false, $schemaTables, true);

    $candidateLoad = pe3_cli_load_candidates((int)$opts['limit']);
    $candidates = is_array($candidateLoad['candidates'] ?? null) ? (array)$candidateLoad['candidates'] : [];
    $rows = [];
    foreach ($candidates as $idx => $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        try {
            $rows[] = pe3_cli_analyze_candidate($db, $candidate, (int)$idx, (int)$opts['min_future_minutes']);
        } catch (Throwable $e) {
            $rows[] = [
                'index' => (int)$idx,
                'candidate_number' => (int)$idx + 1,
                'ready' => false,
                'queue_status' => 'blocked',
                'error' => $e->getMessage(),
                'block_reasons' => ['Candidate analysis failed: ' . $e->getMessage()],
                'summary' => [],
            ];
        }
    }

    $ready = array_values(array_filter($rows, static fn(array $row): bool => !empty($row['ready'])));
    $insertResults = [];
    if (!empty($opts['commit'])) {
        if (!$schemaOk) {
            $insertResults[] = ['status' => 'error', 'error' => 'V3 queue schema is not installed.'];
        } else {
            foreach ($ready as $row) {
                $insertResults[] = pe3_cli_insert_queue_row($db, $row);
            }
        }
    }

    $summary = [
        'ok' => true,
        'version' => PE3_CLI_VERSION,
        'mode' => !empty($opts['commit']) ? 'commit_v3_queue_only' : 'dry_run',
        'started_at' => date(DATE_ATOM),
        'limit' => (int)$opts['limit'],
        'min_future_minutes' => (int)$opts['min_future_minutes'],
        'schema_ok' => $schemaOk,
        'schema_tables' => $schemaTables,
        'candidate_count' => count($rows),
        'ready_count' => count($ready),
        'blocked_count' => count($rows) - count($ready),
        'inserted_count' => count(array_filter($insertResults, static fn(array $r): bool => ($r['status'] ?? '') === 'inserted')),
        'duplicate_count' => count(array_filter($insertResults, static fn(array $r): bool => ($r['status'] ?? '') === 'duplicate_existing')),
        'error_count' => count(array_filter($insertResults, static fn(array $r): bool => ($r['status'] ?? '') === 'error')),
        'safety' => [
            'v3_tables_only' => true,
            'production_submission_jobs' => false,
            'production_submission_attempts' => false,
            'edxeix_server_call' => false,
            'aade_call' => false,
            'email_mark_read_or_deleted' => false,
        ],
    ];

    $publicRows = [];
    foreach ($rows as $row) {
        $publicRows[] = [
            'candidate_number' => $row['candidate_number'] ?? null,
            'ready' => !empty($row['ready']),
            'dedupe_key' => $row['dedupe_key'] ?? '',
            'source_mailbox' => $row['source_mailbox'] ?? '',
            'source_mtime' => $row['source_mtime'] ?? '',
            'summary' => $row['summary'] ?? [],
            'gates' => [
                'parser_ok' => !empty($row['parser_ok']),
                'mapping_ok' => !empty($row['mapping_ok']),
                'future_ok' => !empty($row['future_ok']),
            ],
            'block_reasons' => $row['block_reasons'] ?? [],
            'error' => $row['error'] ?? '',
        ];
    }

    return [
        'help' => false,
        'summary' => $summary,
        'loader' => [
            'ok' => !empty($candidateLoad['ok']),
            'error' => (string)($candidateLoad['error'] ?? ''),
            'checked_dirs' => $candidateLoad['checked_dirs'] ?? [],
            'loader_version' => $candidateLoad['loader_version'] ?? '',
        ],
        'rows' => $publicRows,
        'insert_results' => $insertResults,
    ];
}

/** @param array<string,mixed> $result */
function pe3_cli_print_text(array $result): void
{
    if (!empty($result['help'])) {
        echo (string)$result['text'];
        return;
    }

    $s = (array)($result['summary'] ?? []);
    echo "V3 pre-ride email queue intake " . ($s['version'] ?? PE3_CLI_VERSION) . "\n";
    echo "Mode: " . ($s['mode'] ?? 'dry_run') . "\n";
    echo "Schema OK: " . (!empty($s['schema_ok']) ? 'yes' : 'no') . "\n";
    echo "Candidates: " . (int)($s['candidate_count'] ?? 0) . " | Ready: " . (int)($s['ready_count'] ?? 0) . " | Blocked: " . (int)($s['blocked_count'] ?? 0) . "\n";
    if (($s['mode'] ?? '') === 'commit_v3_queue_only') {
        echo "Inserted: " . (int)($s['inserted_count'] ?? 0) . " | Duplicates: " . (int)($s['duplicate_count'] ?? 0) . " | Errors: " . (int)($s['error_count'] ?? 0) . "\n";
    } else {
        echo "Dry run only. Add --commit to insert ready rows into V3-only queue tables.\n";
    }
    echo "\n";

    foreach ((array)($result['rows'] ?? []) as $row) {
        $summary = (array)($row['summary'] ?? []);
        echo '#' . (int)($row['candidate_number'] ?? 0) . ' ' . (!empty($row['ready']) ? 'READY' : 'BLOCKED') . ' ' . (string)($row['dedupe_key'] ?? '') . "\n";
        echo '  Pickup: ' . (string)($summary['pickup_datetime'] ?? '') . ' (' . (string)($summary['minutes_until'] ?? '') . " min)\n";
        echo '  Transfer: ' . (string)($summary['customer'] ?? '') . ' | ' . (string)($summary['driver'] ?? '') . ' | ' . (string)($summary['vehicle'] ?? '') . "\n";
        echo '  IDs: lessor=' . (string)($summary['lessor_id'] ?? '') . ' driver=' . (string)($summary['driver_id'] ?? '') . ' vehicle=' . (string)($summary['vehicle_id'] ?? '') . ' start=' . (string)($summary['starting_point_id'] ?? '') . "\n";
        $reasons = (array)($row['block_reasons'] ?? []);
        if (!empty($reasons)) {
            echo '  Block: ' . implode(' | ', array_map('strval', $reasons)) . "\n";
        }
    }

    if (!empty($result['insert_results'])) {
        echo "\nInsert results:\n";
        foreach ((array)$result['insert_results'] as $r) {
            echo '- ' . (string)($r['status'] ?? '') . ' ' . (string)($r['dedupe_key'] ?? '') . ' queue_id=' . (string)($r['queue_id'] ?? '') . ' ' . (string)($r['error'] ?? '') . "\n";
        }
    }
}

$options = pe3_cli_options($argv);
try {
    $result = pe3_cli_run($argv);
    if (!empty($options['json']) && empty($result['help'])) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    } else {
        pe3_cli_print_text($result);
    }
    $summary = (array)($result['summary'] ?? []);
    exit(((int)($summary['error_count'] ?? 0)) > 0 ? 1 : 0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'version' => PE3_CLI_VERSION,
        'error' => $e->getMessage(),
        'safety' => [
            'production_submission_jobs' => false,
            'production_submission_attempts' => false,
            'edxeix_server_call' => false,
            'aade_call' => false,
        ],
    ];
    if (!empty($options['json'])) {
        echo json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    } else {
        fwrite(STDERR, 'V3 CLI intake failed: ' . $e->getMessage() . "\n");
    }
    exit(1);
}
