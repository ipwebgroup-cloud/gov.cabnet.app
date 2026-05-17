<?php
/**
 * gov.cabnet.app — EDXEIX pre-ride future candidate diagnostic library v3.2.22
 *
 * Purpose:
 * - Convert a pasted/latest Bolt pre-ride email into a sanitized future EDXEIX candidate preview.
 * - Keep receipt-only Bolt mail rows blocked while allowing a separate pre-ride candidate path.
 * - Optionally capture candidate metadata into the additive edxeix_pre_ride_candidates table.
 *
 * Safety contract:
 * - Default mode is dry-run / analysis only.
 * - No EDXEIX HTTP transport.
 * - No AADE/myDATA calls.
 * - No submission jobs are created.
 * - No normalized_bookings rows are created or changed.
 * - Raw email body is never stored; only parsed fields, hashes, readiness, and payload preview may be stored when --write=1 is explicitly used.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

$__govPrcParser = '/home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php';
if (is_file($__govPrcParser)) { require_once $__govPrcParser; }
$__govPrcLookup = '/home/cabnet/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php';
if (is_file($__govPrcLookup)) { require_once $__govPrcLookup; }
$__govPrcLoader = '/home/cabnet/gov.cabnet.app_app/src/BoltMail/MaildirPreRideEmailLoader.php';
if (is_file($__govPrcLoader)) { require_once $__govPrcLoader; }

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\BoltMail\MaildirPreRideEmailLoader;

if (!function_exists('gov_prc_now')) {
    function gov_prc_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('gov_prc_bool')) {
    function gov_prc_bool($value): bool
    {
        if (is_bool($value)) { return $value; }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gov_prc_json')) {
    function gov_prc_json($value): string
    {
        if (function_exists('gov_bridge_json_encode_db')) {
            return gov_bridge_json_encode_db($value);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
    }
}

if (!function_exists('gov_prc_configured_future_guard_minutes')) {
    function gov_prc_configured_future_guard_minutes(): int
    {
        $config = gov_bridge_load_config();
        return (int)($config['edxeix']['future_start_guard_minutes'] ?? 30);
    }
}

if (!function_exists('gov_prc_effective_future_guard_minutes')) {
    function gov_prc_effective_future_guard_minutes(): int
    {
        return max(30, gov_prc_configured_future_guard_minutes());
    }
}

if (!function_exists('gov_prc_future_guard_passes')) {
    function gov_prc_future_guard_passes(?string $pickupAt, int $guardMinutes): bool
    {
        if (!$pickupAt) { return false; }
        $ts = strtotime($pickupAt);
        return $ts !== false && $ts >= (time() + ($guardMinutes * 60));
    }
}

if (!function_exists('gov_prc_normalize_plate')) {
    function gov_prc_normalize_plate(string $plate): string
    {
        $plate = preg_replace('/\s+/', '', trim($plate)) ?? trim($plate);
        return function_exists('mb_strtoupper') ? mb_strtoupper($plate, 'UTF-8') : strtoupper($plate);
    }
}

if (!function_exists('gov_prc_mapping_lookup')) {
    /**
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    function gov_prc_mapping_lookup(mysqli $db, array $fields): array
    {
        if (!class_exists(EdxeixMappingLookup::class)) {
            return [
                'ok' => false,
                'lessor_id' => '',
                'driver_id' => '',
                'vehicle_id' => '',
                'starting_point_id' => '',
                'messages' => [],
                'warnings' => ['EdxeixMappingLookup class is unavailable.'],
            ];
        }

        try {
            $lookup = new EdxeixMappingLookup($db);
            return $lookup->lookup($fields);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'lessor_id' => '',
                'driver_id' => '',
                'vehicle_id' => '',
                'starting_point_id' => '',
                'messages' => [],
                'warnings' => ['Mapping lookup failed: ' . $e->getMessage()],
            ];
        }
    }
}

if (!function_exists('gov_prc_admin_exclusion_status')) {
    /**
     * @return array{excluded:bool,reason:string,source:string,warnings:array<int,string>}
     */
    function gov_prc_admin_exclusion_status(mysqli $db, string $plate): array
    {
        $plateNorm = gov_prc_normalize_plate($plate);
        $warnings = [];

        if ($plateNorm === 'EMT8640') {
            return [
                'excluded' => true,
                'reason' => 'Mercedes-Benz Sprinter / EMT8640 is permanently Admin Excluded.',
                'source' => 'hard_safety_rule',
                'warnings' => [],
            ];
        }

        if ($plateNorm === '' || !function_exists('gov_bridge_table_exists') || !gov_bridge_table_exists($db, 'mapping_vehicles')) {
            return ['excluded' => false, 'reason' => '', 'source' => '', 'warnings' => $warnings];
        }

        try {
            $columns = gov_bridge_table_columns($db, 'mapping_vehicles');
            if (!isset($columns['plate'])) {
                return ['excluded' => false, 'reason' => '', 'source' => '', 'warnings' => $warnings];
            }

            $flagCols = [
                'admin_excluded', 'is_admin_excluded', 'exclude_from_edxeix', 'edxeix_excluded',
                'never_submit_live', 'no_edxeix', 'is_excluded', 'disabled_for_edxeix',
            ];
            $select = ['id', 'plate'];
            foreach ($flagCols as $col) {
                if (isset($columns[$col])) { $select[] = $col; }
            }
            foreach (['notes', 'admin_notes', 'exclusion_reason', 'block_reason'] as $col) {
                if (isset($columns[$col])) { $select[] = $col; }
            }

            $sql = 'SELECT ' . implode(', ', array_map('gov_bridge_quote_identifier', array_values(array_unique($select))))
                . ' FROM mapping_vehicles WHERE REPLACE(UPPER(plate), " ", "") = ? ORDER BY id DESC LIMIT 1';
            $row = gov_bridge_fetch_one($db, $sql, [$plateNorm]);
            if (!is_array($row)) {
                return ['excluded' => false, 'reason' => '', 'source' => '', 'warnings' => $warnings];
            }

            foreach ($flagCols as $col) {
                if (!array_key_exists($col, $row)) { continue; }
                $value = strtolower(trim((string)$row[$col]));
                if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                    return [
                        'excluded' => true,
                        'reason' => 'Vehicle mapping flag ' . $col . ' blocks automated EDXEIX processing.',
                        'source' => 'mapping_vehicles.' . $col,
                        'warnings' => $warnings,
                    ];
                }
            }

            foreach (['notes', 'admin_notes', 'exclusion_reason', 'block_reason'] as $col) {
                if (!array_key_exists($col, $row)) { continue; }
                $text = strtolower((string)$row[$col]);
                foreach (['admin excluded', 'no edxeix', 'exclude from edxeix', 'never submit', 'blocked from edxeix'] as $needle) {
                    if ($needle !== '' && strpos($text, $needle) !== false) {
                        return [
                            'excluded' => true,
                            'reason' => 'Vehicle mapping note blocks automated EDXEIX processing.',
                            'source' => 'mapping_vehicles.' . $col,
                            'warnings' => $warnings,
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            $warnings[] = 'Admin exclusion lookup warning: ' . $e->getMessage();
        }

        return ['excluded' => false, 'reason' => '', 'source' => '', 'warnings' => $warnings];
    }
}

if (!function_exists('gov_prc_extra_maildirs_from_config')) {
    /** @return array<int,string> */
    function gov_prc_extra_maildirs_from_config(): array
    {
        $config = gov_bridge_load_config();
        $dirs = [];
        foreach (['pre_ride_maildir', 'bolt_pre_ride_maildir'] as $key) {
            $value = $config['mail'][$key] ?? null;
            if (is_string($value) && trim($value) !== '') { $dirs[] = trim($value); }
        }
        foreach (['pre_ride_maildirs', 'bolt_pre_ride_maildirs'] as $key) {
            $many = $config['mail'][$key] ?? null;
            if (is_array($many)) {
                foreach ($many as $dir) {
                    if (is_string($dir) && trim($dir) !== '') { $dirs[] = trim($dir); }
                }
            }
        }
        return array_values(array_unique($dirs));
    }
}

if (!function_exists('gov_prc_source_from_options')) {
    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    function gov_prc_source_from_options(array $options): array
    {
        $warnings = [];
        $text = trim((string)($options['email_text'] ?? ''));
        $sourceLabel = 'pasted_text';
        $sourceType = 'pasted_bolt_pre_ride_email';
        $sourceMtime = '';
        $checkedDirs = [];

        if ($text === '') {
            $file = trim((string)($options['email_file'] ?? ''));
            if ($file !== '') {
                if (!is_file($file) || !is_readable($file)) {
                    $warnings[] = 'Email file is not readable: ' . $file;
                } else {
                    $raw = file_get_contents($file, false, null, 0, 120000);
                    $text = is_string($raw) ? trim($raw) : '';
                    $sourceLabel = basename($file);
                    $sourceType = 'file_bolt_pre_ride_email';
                    $sourceMtime = date('Y-m-d H:i:s', (int)@filemtime($file));
                }
            }
        }

        if ($text === '' && !empty($options['latest_mail'])) {
            if (!class_exists(MaildirPreRideEmailLoader::class)) {
                $warnings[] = 'MaildirPreRideEmailLoader class is unavailable.';
            } else {
                try {
                    $loader = new MaildirPreRideEmailLoader();
                    $loaded = $loader->loadLatest(gov_prc_extra_maildirs_from_config());
                    $checkedDirs = is_array($loaded['checked_dirs'] ?? null) ? $loaded['checked_dirs'] : [];
                    if (!empty($loaded['ok'])) {
                        $text = trim((string)($loaded['email_text'] ?? ''));
                        $sourceLabel = basename((string)($loaded['source'] ?? 'latest_maildir_message'));
                        $sourceType = 'latest_maildir_bolt_pre_ride_email';
                        $sourceMtime = (string)($loaded['source_mtime'] ?? '');
                    } else {
                        $warnings[] = 'Latest Maildir load failed: ' . (string)($loaded['error'] ?? 'unknown error');
                    }
                } catch (Throwable $e) {
                    $warnings[] = 'Latest Maildir load exception: ' . $e->getMessage();
                }
            }
        }

        return [
            'ok' => $text !== '',
            'email_text' => $text,
            'source_type' => $sourceType,
            'source_label' => $sourceLabel,
            'source_mtime' => $sourceMtime,
            'source_hash' => $text !== '' ? hash('sha256', $text) : '',
            'warnings' => $warnings,
            'checked_dirs' => $checkedDirs,
        ];
    }
}

if (!function_exists('gov_prc_build_payload_preview')) {
    /**
     * @param array<string,mixed> $fields
     * @param array<string,mixed> $mapping
     * @return array<string,mixed>
     */
    function gov_prc_build_payload_preview(array $fields, array $mapping): array
    {
        $config = gov_bridge_load_config();
        $broker = (string)($config['edxeix']['default_broker'] ?? '');
        $startingPoint = trim((string)($mapping['starting_point_id'] ?? ''));
        $customer = trim((string)($fields['customer_name'] ?? ''));
        $phone = trim((string)($fields['customer_phone'] ?? ''));
        $lessee = trim($customer . ($phone !== '' ? ' / ' . $phone : ''));

        return [
            'lessor' => trim((string)($mapping['lessor_id'] ?? '')),
            'broker' => $broker,
            'driver' => trim((string)($mapping['driver_id'] ?? '')),
            'vehicle' => trim((string)($mapping['vehicle_id'] ?? '')),
            'starting_point' => $startingPoint,
            'starting_point_id' => $startingPoint,
            'boarding_point' => trim((string)($fields['pickup_address'] ?? '')),
            'disembark_point' => trim((string)($fields['dropoff_address'] ?? '')),
            'lessee' => $lessee,
            'started_at' => trim((string)($fields['pickup_datetime_local'] ?? '')),
            'ended_at' => trim((string)($fields['end_datetime_local'] ?? '')),
            'price' => trim((string)($fields['estimated_price_amount'] ?? '')),
            'coordinates' => '',
            'drafted_at' => gov_prc_now(),
        ];
    }
}

if (!function_exists('gov_prc_payload_summary')) {
    function gov_prc_payload_summary(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);
        return [
            'field_count' => count($payload),
            'fields' => $keys,
            'payload_hash' => hash('sha256', gov_prc_json($payload)),
        ];
    }
}

if (!function_exists('gov_prc_analyze_text')) {
    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    function gov_prc_analyze_text(mysqli $db, array $source): array
    {
        $warnings = is_array($source['warnings'] ?? null) ? $source['warnings'] : [];
        if (!class_exists(BoltPreRideEmailParser::class)) {
            return [
                'ok' => false,
                'classification' => ['code' => 'PARSER_UNAVAILABLE', 'message' => 'Bolt pre-ride email parser class is unavailable.'],
                'source' => $source,
                'candidate' => null,
                'write' => null,
            ];
        }

        $parser = new BoltPreRideEmailParser();
        $parsed = $parser->parse((string)($source['email_text'] ?? ''));
        $fields = is_array($parsed['fields'] ?? null) ? $parsed['fields'] : [];
        $mapping = gov_prc_mapping_lookup($db, $fields);
        $adminExclusion = gov_prc_admin_exclusion_status($db, (string)($fields['vehicle_plate'] ?? ''));
        foreach ($adminExclusion['warnings'] as $warning) { $warnings[] = $warning; }

        $guardConfigured = gov_prc_configured_future_guard_minutes();
        $guardEffective = gov_prc_effective_future_guard_minutes();
        $pickupAt = (string)($fields['pickup_datetime_local'] ?? '');
        $futurePass = gov_prc_future_guard_passes($pickupAt, $guardEffective);
        $payload = gov_prc_build_payload_preview($fields, $mapping);
        $missing = is_array($parsed['missing_required'] ?? null) ? $parsed['missing_required'] : [];
        $parserWarnings = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];
        foreach ($parserWarnings as $warning) { $warnings[] = (string)$warning; }
        $mappingWarnings = is_array($mapping['warnings'] ?? null) ? $mapping['warnings'] : [];

        $blockers = [];
        if (empty($parsed['ok'])) { $blockers[] = 'pre_ride_parser_missing_required_fields'; }
        if ($guardConfigured < 30) { $blockers[] = 'configured_future_guard_below_30_minimum'; }
        if ($pickupAt === '') { $blockers[] = 'pre_ride_missing_pickup_datetime'; }
        elseif (!$futurePass) { $blockers[] = 'pre_ride_pickup_not_' . $guardEffective . '_min_future'; }
        if (empty($mapping['ok'])) { $blockers[] = 'pre_ride_mapping_not_ready'; }
        if (!empty($adminExclusion['excluded'])) { $blockers[] = 'pre_ride_vehicle_admin_excluded'; }
        if (trim((string)($fields['customer_name'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_customer_name'; }
        if (trim((string)($fields['customer_phone'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_customer_phone'; }
        if (trim((string)($fields['pickup_address'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_pickup_address'; }
        if (trim((string)($fields['dropoff_address'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_dropoff_address'; }
        if (trim((string)($fields['end_datetime_local'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_estimated_end_datetime'; }
        if (trim((string)($mapping['lessor_id'] ?? '')) === '') { $blockers[] = 'pre_ride_lessor_not_resolved'; }
        if (trim((string)($mapping['driver_id'] ?? '')) === '') { $blockers[] = 'pre_ride_driver_not_resolved'; }
        if (trim((string)($mapping['vehicle_id'] ?? '')) === '') { $blockers[] = 'pre_ride_vehicle_not_resolved'; }
        if (trim((string)($mapping['starting_point_id'] ?? '')) === '') { $blockers[] = 'pre_ride_starting_point_not_resolved'; }

        $blockers = array_values(array_unique($blockers));
        $ready = empty(array_diff($blockers, ['configured_future_guard_below_30_minimum']));
        $status = $ready ? 'ready' : 'blocked';
        $readiness = $ready ? 'READY_FUTURE_PRE_RIDE_CANDIDATE' : 'BLOCKED_PRE_RIDE_CANDIDATE';

        $candidate = [
            'source_system' => 'bolt_pre_ride_email',
            'source_type' => (string)($source['source_type'] ?? ''),
            'source_label' => (string)($source['source_label'] ?? ''),
            'source_hash' => (string)($source['source_hash'] ?? ''),
            'source_mtime' => (string)($source['source_mtime'] ?? ''),
            'order_reference' => (string)($fields['order_reference'] ?? ''),
            'status' => $status,
            'readiness_status' => $readiness,
            'ready_for_edxeix' => $ready,
            'pickup_datetime' => $pickupAt,
            'estimated_end_datetime' => (string)($fields['end_datetime_local'] ?? ''),
            'customer_name' => (string)($fields['customer_name'] ?? ''),
            'customer_phone' => (string)($fields['customer_phone'] ?? ''),
            'driver_name' => (string)($fields['driver_name'] ?? ''),
            'vehicle_plate' => (string)($fields['vehicle_plate'] ?? ''),
            'pickup_address' => (string)($fields['pickup_address'] ?? ''),
            'dropoff_address' => (string)($fields['dropoff_address'] ?? ''),
            'price_amount' => (string)($fields['estimated_price_amount'] ?? ''),
            'price_currency' => (string)($fields['estimated_price_currency'] ?? ''),
            'configured_future_guard_minutes' => $guardConfigured,
            'effective_future_guard_minutes' => $guardEffective,
            'future_guard_floor_applied' => $guardEffective > $guardConfigured,
            'future_guard_passed' => $futurePass,
            'mapping_ready' => !empty($mapping['ok']),
            'admin_exclusion' => $adminExclusion,
            'safety_blockers' => $blockers,
            'missing_required' => $missing,
            'warnings' => array_values(array_unique($warnings)),
            'mapping_warnings' => $mappingWarnings,
            'mapping' => [
                'lookup_version' => (string)($mapping['lookup_version'] ?? ''),
                'lessor_id' => (string)($mapping['lessor_id'] ?? ''),
                'lessor_source' => (string)($mapping['lessor_source'] ?? ''),
                'driver_id' => (string)($mapping['driver_id'] ?? ''),
                'driver_label' => (string)($mapping['driver_label'] ?? ''),
                'vehicle_id' => (string)($mapping['vehicle_id'] ?? ''),
                'vehicle_label' => (string)($mapping['vehicle_label'] ?? ''),
                'starting_point_id' => (string)($mapping['starting_point_id'] ?? ''),
                'starting_point_label' => (string)($mapping['starting_point_label'] ?? ''),
                'company_trusted_from_edxeix_mapping' => !empty($mapping['company_trusted_from_edxeix_mapping']),
            ],
            'payload_summary' => gov_prc_payload_summary($payload),
            'payload_preview' => $payload,
            'parsed_fields' => $fields,
        ];

        return [
            'ok' => true,
            'classification' => [
                'code' => $ready ? 'PRE_RIDE_READY_CANDIDATE' : 'PRE_RIDE_CANDIDATE_BLOCKED',
                'message' => $ready
                    ? 'Pre-ride email parsed into a future EDXEIX-ready candidate preview. No submit was performed.'
                    : 'Pre-ride email was parsed but is blocked by readiness/safety checks. No submit was performed.',
            ],
            'source' => [
                'source_type' => (string)($source['source_type'] ?? ''),
                'source_label' => (string)($source['source_label'] ?? ''),
                'source_mtime' => (string)($source['source_mtime'] ?? ''),
                'source_hash_16' => substr((string)($source['source_hash'] ?? ''), 0, 16),
                'checked_dirs' => is_array($source['checked_dirs'] ?? null) ? $source['checked_dirs'] : [],
            ],
            'candidate' => $candidate,
            'write' => null,
            'next_action' => $ready
                ? 'Review the candidate, then explicitly capture metadata with --write=1 only after the additive SQL table is installed.'
                : 'Fix the listed blockers or wait for a real future pre-ride email before attempting any one-shot transport.',
        ];
    }
}

if (!function_exists('gov_prc_table_exists')) {
    function gov_prc_table_exists(mysqli $db): bool
    {
        return function_exists('gov_bridge_table_exists') && gov_bridge_table_exists($db, 'edxeix_pre_ride_candidates');
    }
}

if (!function_exists('gov_prc_write_candidate')) {
    /**
     * @param array<string,mixed> $candidate
     * @return array<string,mixed>
     */
    function gov_prc_write_candidate(mysqli $db, array $candidate): array
    {
        if (!gov_prc_table_exists($db)) {
            return [
                'requested' => true,
                'ok' => false,
                'written' => false,
                'message' => 'Table edxeix_pre_ride_candidates does not exist. Run the additive migration first.',
            ];
        }

        $fields = is_array($candidate['parsed_fields'] ?? null) ? $candidate['parsed_fields'] : [];
        $payload = is_array($candidate['payload_preview'] ?? null) ? $candidate['payload_preview'] : [];
        $mapping = is_array($candidate['mapping'] ?? null) ? $candidate['mapping'] : [];
        $blockers = is_array($candidate['safety_blockers'] ?? null) ? $candidate['safety_blockers'] : [];
        $warnings = is_array($candidate['warnings'] ?? null) ? $candidate['warnings'] : [];

        $sql = "
            INSERT INTO edxeix_pre_ride_candidates (
                source_hash, source_type, source_label, source_mtime,
                order_reference, pickup_datetime, estimated_end_datetime,
                customer_name, customer_phone, driver_name, vehicle_plate,
                pickup_address, dropoff_address, price_amount, price_currency,
                status, readiness_status, ready_for_edxeix,
                parsed_fields_json, payload_preview_json, mapping_status_json,
                safety_blockers_json, warnings_json, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                source_type = VALUES(source_type),
                source_label = VALUES(source_label),
                source_mtime = VALUES(source_mtime),
                order_reference = VALUES(order_reference),
                pickup_datetime = VALUES(pickup_datetime),
                estimated_end_datetime = VALUES(estimated_end_datetime),
                customer_name = VALUES(customer_name),
                customer_phone = VALUES(customer_phone),
                driver_name = VALUES(driver_name),
                vehicle_plate = VALUES(vehicle_plate),
                pickup_address = VALUES(pickup_address),
                dropoff_address = VALUES(dropoff_address),
                price_amount = VALUES(price_amount),
                price_currency = VALUES(price_currency),
                status = VALUES(status),
                readiness_status = VALUES(readiness_status),
                ready_for_edxeix = VALUES(ready_for_edxeix),
                parsed_fields_json = VALUES(parsed_fields_json),
                payload_preview_json = VALUES(payload_preview_json),
                mapping_status_json = VALUES(mapping_status_json),
                safety_blockers_json = VALUES(safety_blockers_json),
                warnings_json = VALUES(warnings_json),
                updated_at = NOW()
        ";

        $pickupForDb = trim((string)($candidate['pickup_datetime'] ?? ''));
        $endForDb = trim((string)($candidate['estimated_end_datetime'] ?? ''));
        $params = [
            (string)($candidate['source_hash'] ?? ''),
            (string)($candidate['source_type'] ?? ''),
            (string)($candidate['source_label'] ?? ''),
            (string)($candidate['source_mtime'] ?? ''),
            (string)($candidate['order_reference'] ?? ''),
            $pickupForDb !== '' ? $pickupForDb : null,
            $endForDb !== '' ? $endForDb : null,
            (string)($candidate['customer_name'] ?? ''),
            (string)($candidate['customer_phone'] ?? ''),
            (string)($candidate['driver_name'] ?? ''),
            (string)($candidate['vehicle_plate'] ?? ''),
            (string)($candidate['pickup_address'] ?? ''),
            (string)($candidate['dropoff_address'] ?? ''),
            (string)($candidate['price_amount'] ?? ''),
            (string)($candidate['price_currency'] ?? ''),
            (string)($candidate['status'] ?? 'blocked'),
            (string)($candidate['readiness_status'] ?? 'BLOCKED_PRE_RIDE_CANDIDATE'),
            !empty($candidate['ready_for_edxeix']) ? 1 : 0,
            gov_prc_json($fields),
            gov_prc_json($payload),
            gov_prc_json($mapping),
            gov_prc_json($blockers),
            gov_prc_json($warnings),
        ];

        try {
            $stmt = $db->prepare($sql);
            $types = str_repeat('s', count($params));
            if (function_exists('gov_bridge_bind_params')) {
                gov_bridge_bind_params($stmt, $types, $params);
            } else {
                $refs = [];
                foreach ($params as $idx => $value) {
                    $params[$idx] = (string)$value;
                    $refs[$idx] = &$params[$idx];
                }
                $stmt->bind_param($types, ...$refs);
            }
            $stmt->execute();
            $idRow = gov_bridge_fetch_one($db, 'SELECT id FROM edxeix_pre_ride_candidates WHERE source_hash = ? LIMIT 1', [(string)($candidate['source_hash'] ?? '')]);
            return [
                'requested' => true,
                'ok' => true,
                'written' => true,
                'candidate_id' => (string)($idRow['id'] ?? ''),
                'message' => 'Candidate metadata captured. Raw email body was not stored.',
            ];
        } catch (Throwable $e) {
            return [
                'requested' => true,
                'ok' => false,
                'written' => false,
                'message' => 'Candidate capture failed: ' . $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('gov_prc_run')) {
    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    function gov_prc_run(array $options = []): array
    {
        $db = gov_bridge_db();
        $source = gov_prc_source_from_options($options);
        $writeRequested = !empty($options['write']);

        if (empty($source['ok'])) {
            return [
                'ok' => true,
                'classification' => [
                    'code' => 'NO_PRE_RIDE_EMAIL_SOURCE',
                    'message' => 'No pre-ride email source was provided. Use pasted text, --email-file, or --latest-mail=1.',
                ],
                'source' => [
                    'source_type' => (string)($source['source_type'] ?? ''),
                    'source_label' => (string)($source['source_label'] ?? ''),
                    'warnings' => is_array($source['warnings'] ?? null) ? $source['warnings'] : [],
                    'checked_dirs' => is_array($source['checked_dirs'] ?? null) ? $source['checked_dirs'] : [],
                ],
                'candidate' => null,
                'write' => [
                    'requested' => $writeRequested,
                    'ok' => false,
                    'written' => false,
                    'message' => $writeRequested ? 'Nothing to write because no pre-ride email source was loaded.' : 'Write not requested.',
                ],
                'next_action' => 'Load or paste the next real future Bolt pre-ride email and rerun diagnostics.',
            ];
        }

        $result = gov_prc_analyze_text($db, $source);
        if ($writeRequested && is_array($result['candidate'] ?? null)) {
            $result['write'] = gov_prc_write_candidate($db, $result['candidate']);
        } else {
            $result['write'] = [
                'requested' => false,
                'ok' => true,
                'written' => false,
                'message' => 'Dry-run only. Candidate metadata was not captured.',
            ];
        }
        return $result;
    }
}
