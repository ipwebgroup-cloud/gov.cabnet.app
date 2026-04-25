<?php
/**
 * gov.cabnet.app — Disabled-by-default EDXEIX live submit safety gate.
 *
 * This library prepares the final live-submit control path without enabling it.
 * It performs candidate analysis, duplicate checks, config checks, and payload
 * review support. Live HTTP submission remains blocked unless server-only config
 * explicitly enables it and all runtime safety gates pass.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

if (!function_exists('gov_live_config_path')) {
    function gov_live_config_path(): string
    {
        return '/home/cabnet/gov.cabnet.app_config/live_submit.php';
    }
}

if (!function_exists('gov_live_default_config')) {
    function gov_live_default_config(): array
    {
        return [
            'live_submit_enabled' => false,
            'http_submit_enabled' => false,
            'require_post' => true,
            'require_confirmation_phrase' => true,
            'confirmation_phrase' => 'I UNDERSTAND SUBMIT LIVE TO EDXEIX',
            'require_real_bolt_source' => true,
            'require_future_guard' => true,
            'require_no_lab_or_test_flags' => true,
            'require_no_duplicate_success' => true,
            'allowed_booking_id' => null,
            'allowed_order_reference' => null,
            'edxeix_submit_url' => '',
            'edxeix_form_method' => 'POST',
            'edxeix_session_file' => '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json',
            'curl_timeout_seconds' => 45,
            'write_audit_rows' => true,
            'note' => 'Real config is server-only. Do not commit this file.',
        ];
    }
}

if (!function_exists('gov_live_load_config')) {
    function gov_live_load_config(): array
    {
        $config = gov_live_default_config();
        $file = gov_live_config_path();
        if (is_file($file) && is_readable($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $config = array_replace_recursive($config, $loaded);
            }
        }
        return $config;
    }
}

if (!function_exists('gov_live_value')) {
    function gov_live_value(array $row, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }
        return $default;
    }
}

if (!function_exists('gov_live_has_col')) {
    function gov_live_has_col(array $columns, string $column): bool
    {
        return isset($columns[$column]);
    }
}

if (!function_exists('gov_live_first_col')) {
    function gov_live_first_col(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (gov_live_has_col($columns, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('gov_live_bool')) {
    function gov_live_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gov_live_terminal_status')) {
    function gov_live_terminal_status(string $status): bool
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return false;
        }
        $terminal = [
            'finished', 'completed', 'client_cancelled', 'driver_cancelled',
            'driver_cancelled_after_accept', 'cancelled', 'canceled', 'expired',
            'rejected', 'failed',
        ];
        return in_array($status, $terminal, true)
            || strpos($status, 'cancel') !== false
            || strpos($status, 'finished') !== false
            || strpos($status, 'complete') !== false;
    }
}

if (!function_exists('gov_live_is_lab_or_test_booking')) {
    function gov_live_is_lab_or_test_booking(array $booking): bool
    {
        $source = strtolower((string)gov_live_value($booking, ['source_system', 'source_type', 'source'], ''));
        $ref = strtoupper((string)gov_live_value($booking, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_trip_reference'], ''));
        $isTest = gov_live_bool(gov_live_value($booking, ['is_test_booking'], false));
        $neverLive = gov_live_bool(gov_live_value($booking, ['never_submit_live'], false));
        return $isTest || $neverLive || strpos($source, 'lab') !== false || strpos($ref, 'LAB-') === 0;
    }
}

if (!function_exists('gov_live_is_real_bolt_booking')) {
    function gov_live_is_real_bolt_booking(array $booking): bool
    {
        $source = strtolower((string)gov_live_value($booking, ['source_system', 'source_type', 'source'], ''));
        return strpos($source, 'bolt') !== false && !gov_live_is_lab_or_test_booking($booking);
    }
}

if (!function_exists('gov_live_future_guard_passes')) {
    function gov_live_future_guard_passes(?string $startedAt, ?int $guardMinutes = null): bool
    {
        if (!$startedAt) {
            return false;
        }
        $config = gov_bridge_load_config();
        $guard = $guardMinutes ?? (int)($config['edxeix']['future_start_guard_minutes'] ?? 30);
        $ts = strtotime($startedAt);
        return $ts !== false && $ts >= (time() + ($guard * 60));
    }
}

if (!function_exists('gov_live_recent_bookings')) {
    function gov_live_recent_bookings(mysqli $db, int $limit = 50): array
    {
        if (!gov_bridge_table_exists($db, 'normalized_bookings')) {
            return [];
        }
        $columns = gov_bridge_table_columns($db, 'normalized_bookings');
        $orderCol = gov_live_first_col($columns, ['updated_at', 'created_at', 'started_at', 'id']) ?: 'id';
        $sql = 'SELECT * FROM normalized_bookings ORDER BY ' . gov_bridge_quote_identifier($orderCol) . ' DESC LIMIT ' . (int)$limit;
        return gov_bridge_fetch_all($db, $sql);
    }
}

if (!function_exists('gov_live_booking_by_id')) {
    function gov_live_booking_by_id(mysqli $db, string $bookingId): ?array
    {
        if ($bookingId === '' || !gov_bridge_table_exists($db, 'normalized_bookings')) {
            return null;
        }
        return gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE id = ? LIMIT 1', [$bookingId]);
    }
}

if (!function_exists('gov_live_payload_hash')) {
    function gov_live_payload_hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('gov_live_duplicate_checks')) {
    function gov_live_duplicate_checks(mysqli $db, array $booking, array $payload): array
    {
        $bookingId = (string)gov_live_value($booking, ['id'], '');
        $orderRef = (string)gov_live_value($booking, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_trip_reference'], '');
        $hash = gov_live_payload_hash($payload);
        $blockers = [];
        $details = [
            'booking_id' => $bookingId,
            'order_reference' => $orderRef,
            'payload_hash' => $hash,
            'live_audit_success_count' => 0,
            'submission_attempt_success_count' => 0,
        ];

        if (gov_bridge_table_exists($db, 'edxeix_live_submission_audit')) {
            $columns = gov_bridge_table_columns($db, 'edxeix_live_submission_audit');
            $conditions = [];
            $params = [];
            if ($bookingId !== '' && gov_live_has_col($columns, 'normalized_booking_id')) {
                $conditions[] = 'normalized_booking_id = ?';
                $params[] = $bookingId;
            }
            if ($orderRef !== '' && gov_live_has_col($columns, 'order_reference')) {
                $conditions[] = 'order_reference = ?';
                $params[] = $orderRef;
            }
            if (gov_live_has_col($columns, 'payload_hash')) {
                $conditions[] = 'payload_hash = ?';
                $params[] = $hash;
            }
            if ($conditions) {
                $statusCondition = gov_live_has_col($columns, 'success') ? ' AND success = 1' : '';
                $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM edxeix_live_submission_audit WHERE (' . implode(' OR ', $conditions) . ')' . $statusCondition, $params);
                $details['live_audit_success_count'] = (int)($row['c'] ?? 0);
                if ($details['live_audit_success_count'] > 0) {
                    $blockers[] = 'duplicate_live_audit_success_detected';
                }
            }
        }

        if (gov_bridge_table_exists($db, 'submission_attempts')) {
            $columns = gov_bridge_table_columns($db, 'submission_attempts');
            $conditions = [];
            $params = [];
            if ($bookingId !== '' && gov_live_has_col($columns, 'normalized_booking_id')) {
                $conditions[] = 'normalized_booking_id = ?';
                $params[] = $bookingId;
            }
            if ($orderRef !== '' && gov_live_has_col($columns, 'order_reference')) {
                $conditions[] = 'order_reference = ?';
                $params[] = $orderRef;
            }
            if (gov_live_has_col($columns, 'payload_hash')) {
                $conditions[] = 'payload_hash = ?';
                $params[] = $hash;
            }
            if ($conditions) {
                $successParts = [];
                if (gov_live_has_col($columns, 'success')) { $successParts[] = 'success = 1'; }
                if (gov_live_has_col($columns, 'remote_reference')) { $successParts[] = "(remote_reference IS NOT NULL AND remote_reference <> '')"; }
                if (gov_live_has_col($columns, 'response_status')) { $successParts[] = '(response_status BETWEEN 200 AND 399)'; }
                if (gov_live_has_col($columns, 'http_status')) { $successParts[] = '(http_status BETWEEN 200 AND 399)'; }
                if ($successParts) {
                    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts WHERE (' . implode(' OR ', $conditions) . ') AND (' . implode(' OR ', $successParts) . ')', $params);
                    $details['submission_attempt_success_count'] = (int)($row['c'] ?? 0);
                    if ($details['submission_attempt_success_count'] > 0) {
                        $blockers[] = 'duplicate_submission_attempt_success_detected';
                    }
                }
            }
        }

        return [
            'blockers' => $blockers,
            'details' => $details,
        ];
    }
}

if (!function_exists('gov_live_session_state')) {
    function gov_live_session_state(array $liveConfig): array
    {
        $file = (string)($liveConfig['edxeix_session_file'] ?? '');
        $exists = $file !== '' && is_file($file) && is_readable($file);
        $hasCookie = false;
        $hasCsrf = false;
        $updatedAt = null;
        if ($exists) {
            $raw = file_get_contents($file);
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded)) {
                $hasCookie = trim((string)($decoded['cookie_header'] ?? '')) !== '';
                $hasCsrf = trim((string)($decoded['csrf_token'] ?? '')) !== '';
                $updatedAt = $decoded['updated_at'] ?? $decoded['saved_at'] ?? null;
            }
        }
        return [
            'session_file_configured' => $file !== '',
            'session_file_exists' => $exists,
            'cookie_present' => $hasCookie,
            'csrf_present' => $hasCsrf,
            'updated_at' => $updatedAt,
            'ready' => $exists && $hasCookie && $hasCsrf,
        ];
    }
}

if (!function_exists('gov_live_analyze_booking')) {
    function gov_live_analyze_booking(mysqli $db, array $booking, ?array $liveConfig = null): array
    {
        $liveConfig = $liveConfig ?? gov_live_load_config();
        $config = gov_bridge_load_config();
        $guardMinutes = (int)($config['edxeix']['future_start_guard_minutes'] ?? 30);

        $preview = [];
        $previewError = null;
        try {
            if (!function_exists('gov_build_edxeix_preview_payload')) {
                throw new RuntimeException('gov_build_edxeix_preview_payload() is unavailable.');
            }
            $preview = gov_build_edxeix_preview_payload($db, $booking);
        } catch (Throwable $e) {
            $previewError = $e->getMessage();
        }

        $mapping = is_array($preview['_mapping_status'] ?? null) ? $preview['_mapping_status'] : [];
        $startedAt = (string)gov_live_value($booking, ['started_at'], '');
        $status = (string)gov_live_value($booking, ['order_status', 'status'], '');
        $bookingId = (string)gov_live_value($booking, ['id'], '');
        $orderRef = (string)gov_live_value($booking, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_reference', 'source_trip_id'], '');
        $isLab = gov_live_is_lab_or_test_booking($booking);
        $isRealBolt = gov_live_is_real_bolt_booking($booking);
        $terminal = gov_live_terminal_status($status);
        $futureGuard = gov_live_future_guard_passes($startedAt, $guardMinutes);
        $driverMapped = !empty($mapping['driver_mapped']) || (string)($preview['driver'] ?? '') !== '';
        $vehicleMapped = !empty($mapping['vehicle_mapped']) || (string)($preview['vehicle'] ?? '') !== '';

        $technicalBlockers = [];
        if ($previewError !== null) { $technicalBlockers[] = 'preview_error'; }
        if (!$driverMapped) { $technicalBlockers[] = 'driver_not_mapped'; }
        if (!$vehicleMapped) { $technicalBlockers[] = 'vehicle_not_mapped'; }
        if ($startedAt === '') { $technicalBlockers[] = 'missing_started_at'; }
        elseif (!$futureGuard) { $technicalBlockers[] = 'started_at_not_' . $guardMinutes . '_min_future'; }
        if ($terminal) { $technicalBlockers[] = 'terminal_order_status'; }

        $duplicate = $preview ? gov_live_duplicate_checks($db, $booking, $preview) : ['blockers' => ['missing_payload_for_duplicate_check'], 'details' => []];
        $session = gov_live_session_state($liveConfig);

        $liveBlockers = [];
        if (empty($liveConfig['live_submit_enabled'])) { $liveBlockers[] = 'live_submit_config_disabled'; }
        if (empty($liveConfig['http_submit_enabled'])) { $liveBlockers[] = 'http_submit_config_disabled'; }
        if (!$isRealBolt) { $liveBlockers[] = 'not_real_bolt_source'; }
        if ($isLab) { $liveBlockers[] = 'lab_or_test_booking_blocked'; }
        if (!$session['ready']) { $liveBlockers[] = 'edxeix_session_not_ready'; }
        if (trim((string)($liveConfig['edxeix_submit_url'] ?? '')) === '') { $liveBlockers[] = 'edxeix_submit_url_missing'; }
        if (!empty($liveConfig['allowed_booking_id']) && (string)$liveConfig['allowed_booking_id'] !== $bookingId) { $liveBlockers[] = 'booking_not_explicitly_allowed'; }
        if (!empty($liveConfig['allowed_order_reference']) && (string)$liveConfig['allowed_order_reference'] !== $orderRef) { $liveBlockers[] = 'order_reference_not_explicitly_allowed'; }
        $liveBlockers = array_values(array_unique(array_merge($liveBlockers, $technicalBlockers, $duplicate['blockers'])));

        return [
            'booking_id' => $bookingId,
            'order_reference' => $orderRef,
            'source_system' => (string)gov_live_value($booking, ['source_system', 'source_type', 'source'], ''),
            'status' => $status,
            'started_at' => $startedAt,
            'driver_name' => (string)gov_live_value($booking, ['driver_name', 'external_driver_name'], ''),
            'plate' => (string)gov_live_value($booking, ['vehicle_plate', 'plate'], ''),
            'is_real_bolt' => $isRealBolt,
            'is_lab_or_test' => $isLab,
            'mapping_ready' => $driverMapped && $vehicleMapped,
            'future_guard_passed' => $futureGuard,
            'terminal_status' => $terminal,
            'technical_payload_valid' => empty($technicalBlockers),
            'technical_blockers' => $technicalBlockers,
            'duplicate_check' => $duplicate,
            'session_state' => $session,
            'live_submission_allowed' => empty($liveBlockers),
            'live_blockers' => $liveBlockers,
            'preview_error' => $previewError,
            'payload_hash' => $preview ? gov_live_payload_hash($preview) : '',
            'edxeix_payload_preview' => $preview,
        ];
    }
}

if (!function_exists('gov_live_analyzed_candidates')) {
    function gov_live_analyzed_candidates(mysqli $db, int $limit = 50): array
    {
        $liveConfig = gov_live_load_config();
        $rows = gov_live_recent_bookings($db, $limit);
        $out = [];
        foreach ($rows as $row) {
            $analysis = gov_live_analyze_booking($db, $row, $liveConfig);
            if ($analysis['technical_payload_valid'] || $analysis['is_real_bolt']) {
                $out[] = $analysis;
            }
        }
        return $out;
    }
}

if (!function_exists('gov_live_insert_audit')) {
    function gov_live_insert_audit(mysqli $db, array $analysis, array $request, array $response): int
    {
        if (!gov_bridge_table_exists($db, 'edxeix_live_submission_audit')) {
            return 0;
        }
        return gov_bridge_insert_row($db, 'edxeix_live_submission_audit', [
            'normalized_booking_id' => $analysis['booking_id'],
            'order_reference' => $analysis['order_reference'],
            'source_system' => $analysis['source_system'],
            'payload_hash' => $analysis['payload_hash'],
            'request_payload_json' => gov_bridge_json_encode_db($analysis['edxeix_payload_preview'] ?? []),
            'response_status' => (string)($response['status'] ?? 0),
            'response_body' => (string)($response['body'] ?? ''),
            'response_json' => gov_bridge_json_encode_db($response['json'] ?? []),
            'success' => !empty($response['success']) ? '1' : '0',
            'remote_reference' => (string)($response['remote_reference'] ?? ''),
            'mode' => (string)($request['mode'] ?? 'live_submit_gate'),
            'live_blockers_json' => gov_bridge_json_encode_db($analysis['live_blockers'] ?? []),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

if (!function_exists('gov_live_submit_if_allowed')) {
    function gov_live_submit_if_allowed(mysqli $db, array $booking, string $confirmationPhrase): array
    {
        $liveConfig = gov_live_load_config();
        $analysis = gov_live_analyze_booking($db, $booking, $liveConfig);
        $expected = (string)($liveConfig['confirmation_phrase'] ?? 'I UNDERSTAND SUBMIT LIVE TO EDXEIX');

        if (!empty($liveConfig['require_confirmation_phrase']) && $confirmationPhrase !== $expected) {
            $analysis['live_blockers'][] = 'confirmation_phrase_mismatch';
            $analysis['live_submission_allowed'] = false;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && !empty($liveConfig['require_post'])) {
            $analysis['live_blockers'][] = 'post_required';
            $analysis['live_submission_allowed'] = false;
        }

        if (!$analysis['live_submission_allowed']) {
            return [
                'ok' => false,
                'submitted' => false,
                'blocked' => true,
                'analysis' => $analysis,
                'response' => [
                    'status' => 0,
                    'success' => false,
                    'body' => 'Blocked by live submission safety gate. No EDXEIX HTTP request was performed.',
                    'json' => [
                        'ok' => false,
                        'submitted' => false,
                        'blockers' => $analysis['live_blockers'],
                    ],
                ],
            ];
        }

        // The transport scaffold exists, but the first production submission should
        // still be made only after the exact EDXEIX endpoint/session behavior has
        // been verified with a real future candidate. This extra blocker prevents
        // accidental live HTTP in this preparatory patch.
        $response = [
            'status' => 0,
            'success' => false,
            'remote_reference' => '',
            'body' => 'HTTP live submit transport is intentionally not executed by this preparatory patch.',
            'json' => [
                'ok' => false,
                'submitted' => false,
                'blocker' => 'http_transport_not_enabled_in_this_patch',
                'note' => 'No EDXEIX HTTP request was performed.',
            ],
        ];
        if (!empty($liveConfig['write_audit_rows'])) {
            gov_live_insert_audit($db, $analysis, ['mode' => 'blocked_transport_scaffold'], $response);
        }

        return [
            'ok' => false,
            'submitted' => false,
            'blocked' => true,
            'analysis' => $analysis,
            'response' => $response,
        ];
    }
}
