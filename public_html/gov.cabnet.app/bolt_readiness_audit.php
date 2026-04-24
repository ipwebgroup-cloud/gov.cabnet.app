<?php
/**
 * gov.cabnet.app — Bolt → EDXEIX Readiness Audit
 *
 * SAFETY CONTRACT:
 * - Read-only only.
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not create, update, or delete database rows.
 * - Does not print API keys, DB passwords, cookies, CSRF tokens, or saved sessions.
 */

declare(strict_types=1);

if (!function_exists('gov_readiness_is_direct_request')) {
    function gov_readiness_is_direct_request(): bool
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        return $script !== '' && realpath($script) === realpath(__FILE__);
    }
}

if (!function_exists('gov_readiness_json_response')) {
    function gov_readiness_json_response(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Robots-Tag: noindex, nofollow', true);
        }
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit;
    }
}

if (!function_exists('gov_readiness_now')) {
    function gov_readiness_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('gov_readiness_bool')) {
    function gov_readiness_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('gov_readiness_int')) {
    function gov_readiness_int(string $key, int $default, int $min, int $max): int
    {
        $raw = $_GET[$key] ?? $_POST[$key] ?? $default;
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
        return max($min, min($max, (int)$value));
    }
}

if (!function_exists('gov_readiness_public_root')) {
    function gov_readiness_public_root(): string
    {
        return __DIR__;
    }
}

if (!function_exists('gov_readiness_bridge_candidates')) {
    function gov_readiness_bridge_candidates(): array
    {
        $publicRoot = gov_readiness_public_root();
        $homeGuess = dirname($publicRoot, 2);
        return array_values(array_unique([
            '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php',
            $homeGuess . '/gov.cabnet.app_app/lib/bolt_sync_lib.php',
            dirname($publicRoot) . '/gov.cabnet.app_app/lib/bolt_sync_lib.php',
            $publicRoot . '/../gov.cabnet.app_app/lib/bolt_sync_lib.php',
        ]));
    }
}

if (!function_exists('gov_readiness_bootstrap_bridge')) {
    function gov_readiness_bootstrap_bridge(): array
    {
        $checked = [];
        foreach (gov_readiness_bridge_candidates() as $file) {
            $checked[] = $file;
            if (is_file($file) && is_readable($file)) {
                require_once $file;
                $requiredFunctions = [
                    'gov_bridge_paths',
                    'gov_bridge_load_config',
                    'gov_bridge_db',
                    'gov_bridge_table_exists',
                    'gov_bridge_table_columns',
                    'gov_bridge_quote_identifier',
                    'gov_bridge_fetch_one',
                    'gov_bridge_fetch_all',
                ];
                $missing = [];
                foreach ($requiredFunctions as $fn) {
                    if (!function_exists($fn)) {
                        $missing[] = $fn;
                    }
                }
                return [
                    'ok' => empty($missing),
                    'loaded' => empty($missing),
                    'path' => $file,
                    'checked' => $checked,
                    'missing_functions' => $missing,
                ];
            }
        }

        return [
            'ok' => false,
            'loaded' => false,
            'path' => null,
            'checked' => $checked,
            'missing_functions' => ['bolt_sync_lib.php not found/readable'],
        ];
    }
}

if (!function_exists('gov_readiness_paths')) {
    function gov_readiness_paths(): array
    {
        if (function_exists('gov_bridge_paths')) {
            return gov_bridge_paths();
        }
        return [
            'home' => '/home/cabnet',
            'public' => '/home/cabnet/public_html/gov.cabnet.app',
            'app' => '/home/cabnet/gov.cabnet.app_app',
            'config' => '/home/cabnet/gov.cabnet.app_config',
            'sql' => '/home/cabnet/gov.cabnet.app_sql',
        ];
    }
}

if (!function_exists('gov_readiness_file_checks')) {
    function gov_readiness_file_checks(array $paths): array
    {
        $files = [
            ['key' => 'public.bolt_sync_reference', 'path' => $paths['public'] . '/bolt_sync_reference.php', 'required' => true],
            ['key' => 'public.bolt_sync_orders', 'path' => $paths['public'] . '/bolt_sync_orders.php', 'required' => true],
            ['key' => 'public.bolt_edxeix_preflight', 'path' => $paths['public'] . '/bolt_edxeix_preflight.php', 'required' => true],
            ['key' => 'public.bolt_jobs_queue', 'path' => $paths['public'] . '/bolt_jobs_queue.php', 'required' => true],
            ['key' => 'public.bolt_stage_edxeix_jobs', 'path' => $paths['public'] . '/bolt_stage_edxeix_jobs.php', 'required' => true],
            ['key' => 'public.bolt_readiness_audit', 'path' => $paths['public'] . '/bolt_readiness_audit.php', 'required' => true],
            ['key' => 'ops.index', 'path' => $paths['public'] . '/ops/index.php', 'required' => true],
            ['key' => 'ops.bolt_live', 'path' => $paths['public'] . '/ops/bolt-live.php', 'required' => true],
            ['key' => 'ops.jobs', 'path' => $paths['public'] . '/ops/jobs.php', 'required' => true],
            ['key' => 'ops.readiness', 'path' => $paths['public'] . '/ops/readiness.php', 'required' => true],
            ['key' => 'app.lib.bolt_sync_lib', 'path' => $paths['app'] . '/lib/bolt_sync_lib.php', 'required' => true],
            ['key' => 'config.config_php', 'path' => $paths['config'] . '/config.php', 'required' => true],
            ['key' => 'config.bolt_php', 'path' => $paths['config'] . '/bolt.php', 'required' => false],
        ];

        $out = [];
        foreach ($files as $file) {
            $exists = is_file($file['path']);
            $out[] = [
                'key' => $file['key'],
                'path' => $file['path'],
                'required' => $file['required'],
                'exists' => $exists,
                'readable' => $exists ? is_readable($file['path']) : false,
            ];
        }
        return $out;
    }
}

if (!function_exists('gov_readiness_public_helper_files')) {
    function gov_readiness_public_helper_files(array $paths): array
    {
        $helpers = [
            'bolt_schema_fix.php' => 'temporary schema repair helper; delete after successful verification',
            'bolt_time_columns_fix.php' => 'temporary time-column repair helper; delete after successful verification',
            'bolt_apply_verified_mappings.php' => 'temporary mapping repair helper; delete after successful verification',
            'bolt_cleanup_placeholders.php' => 'temporary cleanup helper; delete after successful verification',
            'bolt_cleanup_lab_jobs.php' => 'temporary cleanup helper; delete after successful verification',
            'bolt_lab_future_candidate.php' => 'LAB-only helper; do not keep public outside active local testing',
            'bootstrap_path_check.php' => 'temporary path/bootstrap diagnostic; delete after successful verification',
        ];

        $out = [];
        foreach ($helpers as $file => $purpose) {
            $path = $paths['public'] . '/' . $file;
            $out[] = [
                'file' => $file,
                'exists' => is_file($path),
                'purpose' => $purpose,
                'recommend_delete_if_exists' => true,
            ];
        }
        return $out;
    }
}

if (!function_exists('gov_readiness_cols')) {
    function gov_readiness_cols(array $columns): array
    {
        $keys = array_keys($columns);
        if ($keys && is_string($keys[0])) {
            return $keys;
        }
        return array_values(array_map('strval', $columns));
    }
}

if (!function_exists('gov_readiness_has_col')) {
    function gov_readiness_has_col(array $columns, string $column): bool
    {
        return isset($columns[$column]) || in_array($column, gov_readiness_cols($columns), true);
    }
}

if (!function_exists('gov_readiness_first_col')) {
    function gov_readiness_first_col(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (gov_readiness_has_col($columns, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('gov_readiness_q')) {
    function gov_readiness_q(string $identifier): string
    {
        if (function_exists('gov_bridge_quote_identifier')) {
            return gov_bridge_quote_identifier($identifier);
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Unsafe SQL identifier: ' . $identifier);
        }
        return '`' . $identifier . '`';
    }
}

if (!function_exists('gov_readiness_count')) {
    function gov_readiness_count(mysqli $db, string $sql, array $params = []): int
    {
        $row = gov_bridge_fetch_one($db, $sql, $params);
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('gov_readiness_order_col')) {
    function gov_readiness_order_col(array $columns): string
    {
        foreach (['updated_at', 'created_at', 'queued_at', 'available_at', 'fetched_at', 'captured_at', 'id'] as $col) {
            if (gov_readiness_has_col($columns, $col)) {
                return $col;
            }
        }
        $all = gov_readiness_cols($columns);
        return $all[0] ?? 'id';
    }
}

if (!function_exists('gov_readiness_terminal_status')) {
    function gov_readiness_terminal_status(string $status): bool
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return false;
        }
        $terminal = [
            'finished',
            'completed',
            'client_cancelled',
            'driver_cancelled',
            'driver_cancelled_after_accept',
            'cancelled',
            'canceled',
            'expired',
            'rejected',
            'failed',
        ];
        return in_array($status, $terminal, true)
            || strpos($status, 'cancel') !== false
            || strpos($status, 'finished') !== false
            || strpos($status, 'complete') !== false;
    }
}

if (!function_exists('gov_readiness_value')) {
    function gov_readiness_value(array $row, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }
        return $default;
    }
}

if (!function_exists('gov_readiness_is_lab')) {
    function gov_readiness_is_lab(array $row): bool
    {
        $source = strtolower((string)gov_readiness_value($row, ['source_system', 'source_type', 'source'], ''));
        $ref = strtoupper((string)gov_readiness_value($row, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_trip_reference'], ''));
        return strpos($source, 'lab') !== false || strpos($ref, 'LAB-FUTURE-') === 0 || strpos($ref, 'LAB-') === 0;
    }
}

if (!function_exists('gov_readiness_table_requirements')) {
    function gov_readiness_table_requirements(): array
    {
        return [
            'mapping_drivers' => [
                'hard' => ['id'],
                'any_of' => [
                    'source identifier' => ['source_system', 'source_type', 'source'],
                    'Bolt driver UUID' => ['external_driver_id', 'driver_external_id', 'driver_uuid'],
                    'EDXEIX driver ID' => ['edxeix_driver_id', 'driver_id'],
                ],
            ],
            'mapping_vehicles' => [
                'hard' => ['id'],
                'any_of' => [
                    'source identifier' => ['source_system', 'source_type', 'source'],
                    'Bolt vehicle UUID or plate' => ['external_vehicle_id', 'vehicle_external_id', 'vehicle_uuid', 'plate', 'vehicle_plate'],
                    'EDXEIX vehicle ID' => ['edxeix_vehicle_id', 'vehicle_id'],
                ],
            ],
            'bolt_raw_payloads' => [
                'hard' => ['id'],
                'any_of' => [
                    'source identifier' => ['source_system', 'source_type', 'source'],
                    'external reference' => ['external_reference', 'source_id', 'order_reference'],
                    'payload JSON' => ['payload_json', 'raw_json'],
                    'captured timestamp' => ['fetched_at', 'captured_at', 'created_at'],
                ],
            ],
            'normalized_bookings' => [
                'hard' => ['id', 'started_at'],
                'any_of' => [
                    'source identifier' => ['source_system', 'source_type', 'source'],
                    'order reference' => ['order_reference', 'external_order_id', 'source_trip_id', 'source_trip_reference'],
                    'status' => ['status', 'order_status'],
                    'driver reference' => ['driver_external_id', 'driver_uuid', 'driver_name'],
                    'vehicle reference' => ['vehicle_external_id', 'vehicle_uuid', 'vehicle_plate', 'plate'],
                    'mapping flag' => ['edxeix_ready'],
                    'price' => ['price'],
                ],
            ],
            'submission_jobs' => [
                'hard' => ['id', 'status'],
                'any_of' => [
                    'booking reference' => ['normalized_booking_id', 'booking_id'],
                    'job timestamp' => ['queued_at', 'created_at', 'available_at'],
                ],
            ],
            'submission_attempts' => [
                'hard' => ['id', 'created_at'],
                'any_of' => [
                    'job reference' => ['submission_job_id', 'job_id'],
                    'payload' => ['request_payload_json', 'payload_json'],
                    'result indicator' => ['status', 'attempt_status', 'success', 'response_status', 'http_status'],
                ],
            ],
        ];
    }
}

if (!function_exists('gov_readiness_table_info')) {
    function gov_readiness_table_info(mysqli $db, string $table, array $requirements): array
    {
        $exists = gov_bridge_table_exists($db, $table);
        $columns = $exists ? gov_bridge_table_columns($db, $table) : [];
        $colNames = gov_readiness_cols($columns);
        $missingHard = [];
        foreach (($requirements['hard'] ?? []) as $col) {
            if (!gov_readiness_has_col($columns, $col)) {
                $missingHard[] = $col;
            }
        }

        $missingGroups = [];
        foreach (($requirements['any_of'] ?? []) as $label => $choices) {
            $found = false;
            foreach ($choices as $choice) {
                if (gov_readiness_has_col($columns, $choice)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingGroups[] = [
                    'label' => is_string($label) ? $label : 'one of',
                    'any_of' => $choices,
                ];
            }
        }

        return [
            'exists' => $exists,
            'columns' => $colNames,
            'missing_required_columns' => $missingHard,
            'missing_required_column_groups' => $missingGroups,
            'ok' => $exists && empty($missingHard) && empty($missingGroups),
        ];
    }
}

if (!function_exists('gov_readiness_mapping_counts')) {
    function gov_readiness_mapping_counts(mysqli $db, string $table, string $edxeixColumn, array $uuidCandidates): array
    {
        if (!gov_bridge_table_exists($db, $table)) {
            return ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'placeholder' => 0];
        }
        $columns = gov_bridge_table_columns($db, $table);
        $total = gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM ' . gov_readiness_q($table));
        $mapped = 0;
        if (gov_readiness_has_col($columns, $edxeixColumn)) {
            $mapped = gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM ' . gov_readiness_q($table) . ' WHERE ' . gov_readiness_q($edxeixColumn) . ' IS NOT NULL AND ' . gov_readiness_q($edxeixColumn) . " <> '' AND " . gov_readiness_q($edxeixColumn) . ' <> 0');
        }
        $placeholder = 0;
        $uuidCol = gov_readiness_first_col($columns, $uuidCandidates);
        if ($uuidCol !== null) {
            $placeholder = gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM ' . gov_readiness_q($table) . ' WHERE ' . gov_readiness_q($uuidCol) . " LIKE '%-test-%'");
        }
        return [
            'total' => $total,
            'mapped' => $mapped,
            'unmapped' => max(0, $total - $mapped),
            'placeholder' => $placeholder,
        ];
    }
}

if (!function_exists('gov_readiness_analyze_booking')) {
    function gov_readiness_analyze_booking(mysqli $db, array $booking, int $guardMinutes): array
    {
        $status = (string)gov_readiness_value($booking, ['order_status', 'status'], '');
        $startedAt = (string)gov_readiness_value($booking, ['started_at'], '');
        $source = (string)gov_readiness_value($booking, ['source_system', 'source_type', 'source'], '');
        $orderRef = (string)gov_readiness_value($booking, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_trip_reference'], '');
        $preview = [];
        $previewError = null;

        try {
            if (function_exists('gov_build_edxeix_preview_payload')) {
                $preview = gov_build_edxeix_preview_payload($db, $booking);
            }
        } catch (Throwable $e) {
            $previewError = $e->getMessage();
        }

        $mapping = is_array($preview['_mapping_status'] ?? null) ? $preview['_mapping_status'] : [];
        $driverMapped = !empty($mapping['driver_mapped']) || (string)($preview['driver'] ?? '') !== '' || !empty($booking['edxeix_driver_id']);
        $vehicleMapped = !empty($mapping['vehicle_mapped']) || (string)($preview['vehicle'] ?? '') !== '' || !empty($booking['edxeix_vehicle_id']);
        if (!$driverMapped && isset($booking['edxeix_ready'])) {
            $driverMapped = ((string)$booking['edxeix_ready'] === '1');
        }
        if (!$vehicleMapped && isset($booking['edxeix_ready'])) {
            $vehicleMapped = ((string)$booking['edxeix_ready'] === '1');
        }

        $terminal = gov_readiness_terminal_status($status);
        $isLab = gov_readiness_is_lab($booking);
        $futureGuard = false;
        if ($startedAt !== '') {
            $ts = strtotime($startedAt);
            $futureGuard = $ts !== false && $ts >= (time() + ($guardMinutes * 60));
        }

        $blockers = [];
        if ($isLab) { $blockers[] = 'lab_row'; }
        if (!$driverMapped) { $blockers[] = 'driver_not_mapped'; }
        if (!$vehicleMapped) { $blockers[] = 'vehicle_not_mapped'; }
        if ($startedAt === '') { $blockers[] = 'missing_started_at'; }
        elseif (!$futureGuard) { $blockers[] = 'started_at_not_' . $guardMinutes . '_min_future'; }
        if ($terminal) { $blockers[] = 'terminal_order_status'; }
        if ($previewError !== null) { $blockers[] = 'preview_error'; }

        return [
            'id' => gov_readiness_value($booking, ['id'], ''),
            'source_system' => $source,
            'order_reference' => $orderRef,
            'status' => $status,
            'started_at' => $startedAt,
            'ended_at' => gov_readiness_value($booking, ['ended_at'], ''),
            'driver_name' => gov_readiness_value($booking, ['driver_name'], ''),
            'plate' => gov_readiness_value($booking, ['vehicle_plate', 'plate'], ''),
            'mapping_ready' => $driverMapped && $vehicleMapped,
            'future_guard_passed' => $futureGuard,
            'terminal_status' => $terminal,
            'is_lab_row' => $isLab,
            'submission_safe' => empty($blockers),
            'blockers' => $blockers,
            'preview_error' => $previewError,
        ];
    }
}

if (!function_exists('gov_readiness_lab_count')) {
    function gov_readiness_lab_count(mysqli $db, string $table): int
    {
        if (!gov_bridge_table_exists($db, $table)) {
            return 0;
        }
        $columns = gov_bridge_table_columns($db, $table);
        $conditions = [];
        if ($col = gov_readiness_first_col($columns, ['source_system', 'source_type', 'source'])) {
            $conditions[] = gov_readiness_q($col) . " = 'bolt_lab'";
        }
        if ($col = gov_readiness_first_col($columns, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_trip_reference'])) {
            $conditions[] = gov_readiness_q($col) . " LIKE 'LAB-FUTURE-%'";
            $conditions[] = gov_readiness_q($col) . " LIKE 'LAB-%'";
        }
        if (!$conditions) {
            return 0;
        }
        return gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM ' . gov_readiness_q($table) . ' WHERE (' . implode(' OR ', $conditions) . ')');
    }
}

if (!function_exists('gov_readiness_submission_attempt_safety')) {
    function gov_readiness_submission_attempt_safety(mysqli $db): array
    {
        $out = [
            'total' => 0,
            'dry_run_indicated' => 0,
            'confirmed_live_indicated' => 0,
            'unclassified' => 0,
            'confidence' => 'no_attempt_rows',
        ];
        if (!gov_bridge_table_exists($db, 'submission_attempts')) {
            $out['confidence'] = 'table_missing';
            return $out;
        }
        $columns = gov_bridge_table_columns($db, 'submission_attempts');
        $out['total'] = gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM `submission_attempts`');
        if ($out['total'] === 0) {
            return $out;
        }

        $dryConditions = [];
        if (gov_readiness_has_col($columns, 'mode')) { $dryConditions[] = "`mode` LIKE '%dry%'"; }
        if (gov_readiness_has_col($columns, 'is_dry_run')) { $dryConditions[] = "`is_dry_run` = 1"; }
        foreach (['status', 'state', 'attempt_status'] as $col) {
            if (gov_readiness_has_col($columns, $col)) {
                $dryConditions[] = gov_readiness_q($col) . " LIKE 'dry_run%'";
                $dryConditions[] = gov_readiness_q($col) . " IN ('blocked_by_preflight','dry_run_validated')";
            }
        }
        foreach (['notes', 'error_message', 'response_body', 'response_payload_json', 'response_json'] as $col) {
            if (gov_readiness_has_col($columns, $col)) {
                $dryConditions[] = gov_readiness_q($col) . " LIKE '%DRY RUN%'";
                $dryConditions[] = gov_readiness_q($col) . " LIKE '%No EDXEIX HTTP request%'";
            }
        }
        if ($dryConditions) {
            $out['dry_run_indicated'] = gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM `submission_attempts` WHERE (' . implode(' OR ', $dryConditions) . ')');
        }

        $liveConditions = [];
        if (gov_readiness_has_col($columns, 'success')) { $liveConditions[] = '`success` = 1'; }
        if (gov_readiness_has_col($columns, 'response_status')) { $liveConditions[] = '(`response_status` BETWEEN 200 AND 599)'; }
        if (gov_readiness_has_col($columns, 'http_status')) { $liveConditions[] = '(`http_status` BETWEEN 200 AND 599)'; }
        if (gov_readiness_has_col($columns, 'remote_reference')) { $liveConditions[] = "(`remote_reference` IS NOT NULL AND `remote_reference` <> '')"; }
        if ($liveConditions) {
            $out['confirmed_live_indicated'] = gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM `submission_attempts` WHERE (' . implode(' OR ', $liveConditions) . ')');
        }

        $out['unclassified'] = max(0, $out['total'] - $out['dry_run_indicated'] - $out['confirmed_live_indicated']);
        if ($out['confirmed_live_indicated'] > 0) {
            $out['confidence'] = 'possible_live_attempts_detected_by_columns';
        } elseif ($out['unclassified'] > 0) {
            $out['confidence'] = 'no_live_success_detected_but_some_attempt_rows_are_unclassified';
        } else {
            $out['confidence'] = 'all_attempt_rows_indicate_dry_run_or_blocked';
        }
        return $out;
    }
}

if (!function_exists('gov_readiness_recent_bookings')) {
    function gov_readiness_recent_bookings(mysqli $db, int $hours, int $limit, int $guardMinutes): array
    {
        $out = [
            'window_hours' => $hours,
            'total_recent_rows' => 0,
            'analyzed_rows' => 0,
            'mapping_ready_rows' => 0,
            'submission_safe_rows' => 0,
            'blocked_rows' => 0,
            'lab_rows' => 0,
            'terminal_rows' => 0,
            'future_guard_passed_rows' => 0,
            'rows' => [],
        ];
        if (!gov_bridge_table_exists($db, 'normalized_bookings')) {
            return $out;
        }
        $columns = gov_bridge_table_columns($db, 'normalized_bookings');
        $where = '1=1';
        $params = [];
        $dateCol = gov_readiness_first_col($columns, ['updated_at', 'created_at', 'started_at', 'drafted_at']);
        if ($dateCol !== null) {
            $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));
            $where = gov_readiness_q($dateCol) . ' >= ?';
            $params[] = $cutoff;
        }
        $out['total_recent_rows'] = gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM `normalized_bookings` WHERE ' . $where, $params);
        $orderCol = gov_readiness_order_col($columns);
        $rows = gov_bridge_fetch_all($db, 'SELECT * FROM `normalized_bookings` WHERE ' . $where . ' ORDER BY ' . gov_readiness_q($orderCol) . ' DESC LIMIT ' . (int)$limit, $params);
        foreach ($rows as $row) {
            $analysis = gov_readiness_analyze_booking($db, $row, $guardMinutes);
            $out['rows'][] = $analysis;
            $out['analyzed_rows']++;
            if ($analysis['mapping_ready']) { $out['mapping_ready_rows']++; }
            if ($analysis['submission_safe']) { $out['submission_safe_rows']++; }
            else { $out['blocked_rows']++; }
            if ($analysis['is_lab_row']) { $out['lab_rows']++; }
            if ($analysis['terminal_status']) { $out['terminal_rows']++; }
            if ($analysis['future_guard_passed']) { $out['future_guard_passed_rows']++; }
        }
        return $out;
    }
}

if (!function_exists('gov_readiness_build_audit')) {
    function gov_readiness_build_audit(array $options = []): array
    {
        $generatedAt = gov_readiness_now();
        $recentHours = (int)($options['recent_hours'] ?? gov_readiness_int('recent_hours', 168, 1, 2160));
        $limit = (int)($options['limit'] ?? gov_readiness_int('limit', 30, 1, 200));
        $analysisLimit = max($limit, min(500, (int)($options['analysis_limit'] ?? 200)));

        $warnings = [];
        $recommendations = [];
        $blocking = [];
        $cautions = [];

        $bridge = gov_readiness_bootstrap_bridge();
        $paths = gov_readiness_paths();
        $fileChecks = gov_readiness_file_checks($paths);
        $helperFiles = gov_readiness_public_helper_files($paths);

        foreach ($fileChecks as $file) {
            if ($file['required'] && (!$file['exists'] || !$file['readable'])) {
                $blocking[] = 'Required file missing or unreadable: ' . $file['key'];
            }
        }
        foreach ($helperFiles as $helper) {
            if ($helper['exists']) {
                $cautions[] = 'Temporary public helper exists: ' . $helper['file'];
                $recommendations[] = 'Delete public temporary helper after verification: ' . $helper['file'];
            }
        }

        $audit = [
            'ok' => false,
            'script' => 'bolt_readiness_audit.php',
            'generated_at' => $generatedAt,
            'read_only' => true,
            'safety_contract' => [
                'calls_bolt' => false,
                'calls_edxeix' => false,
                'creates_jobs' => false,
                'writes_database' => false,
                'prints_secrets' => false,
            ],
            'bootstrap' => $bridge,
            'paths' => [
                'public' => $paths['public'] ?? '',
                'app' => $paths['app'] ?? '',
                'config' => $paths['config'] ?? '',
                'sql' => $paths['sql'] ?? '',
            ],
            'file_checks' => $fileChecks,
            'public_helper_files' => $helperFiles,
            'config_state' => [],
            'tables' => [],
            'reference_counts' => [],
            'recent_bookings' => [],
            'lab_safety' => [],
            'submission_attempt_safety' => [],
            'warnings' => [],
            'recommendations' => [],
            'blocking_issues' => [],
            'cautions' => [],
            'verdict' => 'NOT_READY',
            'verdict_reason' => '',
            'note' => 'Read-only audit only. No Bolt request, EDXEIX request, database write, job creation, or live submission action was performed.',
        ];

        if (!$bridge['ok']) {
            $blocking[] = 'Bridge bootstrap failed: bolt_sync_lib.php is not loaded with required functions.';
            $audit['blocking_issues'] = $blocking;
            $audit['warnings'] = $warnings;
            $audit['recommendations'] = $recommendations;
            $audit['cautions'] = $cautions;
            $audit['verdict_reason'] = 'Cannot audit database/config until the bridge library loads.';
            return $audit;
        }

        try {
            $config = gov_bridge_load_config();
            if (!empty($config['app']['timezone'])) {
                date_default_timezone_set((string)$config['app']['timezone']);
            }
            $db = gov_bridge_db();
        } catch (Throwable $e) {
            $blocking[] = 'Config/database bootstrap failed: ' . $e->getMessage();
            $audit['blocking_issues'] = $blocking;
            $audit['warnings'] = $warnings;
            $audit['recommendations'] = $recommendations;
            $audit['cautions'] = $cautions;
            $audit['verdict_reason'] = 'Database/config bootstrap is required for readiness.';
            return $audit;
        }

        $app = $config['app'] ?? [];
        $bolt = $config['bolt'] ?? [];
        $edxeix = $config['edxeix'] ?? [];
        $expectedBoltBase = 'https://node.bolt.eu/fleet-integration-gateway';
        $expectedTokenUrl = 'https://oidc.bolt.eu/token';
        $expectedScope = 'fleet-integration:api';
        $boltBase = (string)($bolt['api_base'] ?? $bolt['base_url'] ?? '');
        $guardMinutes = (int)($edxeix['future_start_guard_minutes'] ?? 30);
        $dryRun = gov_readiness_bool($app['dry_run'] ?? true, true);
        $debug = gov_readiness_bool($app['debug'] ?? false, false);

        $audit['config_state'] = [
            'app_env' => (string)($app['env'] ?? ''),
            'debug_enabled' => $debug,
            'dry_run_enabled' => $dryRun,
            'bolt_base_verified' => $boltBase === $expectedBoltBase,
            'bolt_token_endpoint_verified' => (string)($bolt['token_url'] ?? '') === $expectedTokenUrl,
            'bolt_scope_verified' => (string)($bolt['scope'] ?? '') === $expectedScope,
            'bolt_credentials_present' => !empty($bolt['client_id']) && !empty($bolt['client_secret']),
            'bolt_company_id_present' => !empty($bolt['company_id']) || !empty($bolt['company_ids']),
            'edxeix_lessor_present' => !empty($edxeix['lessor_id']) || !empty($edxeix['lessor']),
            'edxeix_default_starting_point_present' => !empty($edxeix['default_starting_point_id']) || !empty($edxeix['starting_point_id']),
            'future_start_guard_minutes' => $guardMinutes,
            'secrets_redacted' => true,
        ];

        if ($debug) { $warnings[] = 'app.debug is enabled. Keep debug=false on production.'; $cautions[] = 'debug_enabled'; }
        if (!$dryRun) { $blocking[] = 'app.dry_run is disabled. Keep dry_run=true until an explicitly approved live submit window.'; }
        if ($boltBase !== $expectedBoltBase) { $warnings[] = 'Bolt base URL is not the verified Fleet Integration gateway used by the current sync flow.'; $cautions[] = 'bolt_base_url_not_verified'; }
        if ((string)($bolt['token_url'] ?? '') !== $expectedTokenUrl) { $warnings[] = 'Bolt token endpoint is not the verified OIDC endpoint.'; $cautions[] = 'bolt_token_url_not_verified'; }
        if ((string)($bolt['scope'] ?? '') !== $expectedScope) { $warnings[] = 'Bolt scope is not fleet-integration:api.'; $cautions[] = 'bolt_scope_not_verified'; }
        if (empty($bolt['client_id']) || empty($bolt['client_secret'])) { $blocking[] = 'Bolt credentials are not present in external config.'; }
        if (empty($edxeix['lessor_id']) && empty($edxeix['lessor'])) { $blocking[] = 'EDXEIX lessor/default company ID is not configured.'; }
        if (empty($edxeix['default_starting_point_id']) && empty($edxeix['starting_point_id'])) { $blocking[] = 'EDXEIX default starting point ID is not configured.'; }

        $requirements = gov_readiness_table_requirements();
        foreach ($requirements as $table => $req) {
            $info = gov_readiness_table_info($db, $table, $req);
            $audit['tables'][$table] = $info;
            if (!$info['exists']) {
                $blocking[] = 'Required table missing: ' . $table;
            } elseif (!$info['ok']) {
                $blocking[] = 'Required table has missing columns/groups: ' . $table;
            }
        }

        $audit['reference_counts'] = [
            'drivers' => gov_readiness_mapping_counts($db, 'mapping_drivers', 'edxeix_driver_id', ['external_driver_id', 'driver_external_id', 'driver_uuid']),
            'vehicles' => gov_readiness_mapping_counts($db, 'mapping_vehicles', 'edxeix_vehicle_id', ['external_vehicle_id', 'vehicle_external_id', 'vehicle_uuid', 'plate', 'vehicle_plate']),
        ];
        if (($audit['reference_counts']['drivers']['mapped'] ?? 0) < 1) { $cautions[] = 'no_mapped_drivers'; }
        if (($audit['reference_counts']['vehicles']['mapped'] ?? 0) < 1) { $cautions[] = 'no_mapped_vehicles'; }
        if (($audit['reference_counts']['drivers']['placeholder'] ?? 0) > 0 || ($audit['reference_counts']['vehicles']['placeholder'] ?? 0) > 0) {
            $warnings[] = 'Placeholder drv-test/veh-test mapping rows are still present.';
            $cautions[] = 'placeholder_mappings_present';
        }

        $audit['recent_bookings'] = gov_readiness_recent_bookings($db, $recentHours, $analysisLimit, $guardMinutes);
        $labBookings = gov_readiness_lab_count($db, 'normalized_bookings');
        $labJobs = gov_readiness_lab_count($db, 'submission_jobs');
        $audit['lab_safety'] = [
            'normalized_lab_rows' => $labBookings,
            'staged_lab_jobs' => $labJobs,
            'ok' => $labBookings === 0 && $labJobs === 0,
        ];
        if ($labBookings > 0) { $blocking[] = 'LAB normalized booking rows still exist.'; }
        if ($labJobs > 0) { $blocking[] = 'LAB staged submission jobs still exist.'; }

        $audit['submission_attempt_safety'] = gov_readiness_submission_attempt_safety($db);
        if (($audit['submission_attempt_safety']['confirmed_live_indicated'] ?? 0) > 0) {
            $blocking[] = 'Submission attempts table contains rows that look like real/live HTTP results.';
        }
        if (($audit['submission_attempt_safety']['unclassified'] ?? 0) > 0) {
            $warnings[] = 'Some submission_attempt rows are not clearly marked as dry-run; inspect before any live test.';
            $cautions[] = 'unclassified_attempt_rows';
        }

        $jobsTotal = 0;
        if (gov_bridge_table_exists($db, 'submission_jobs')) {
            $jobsTotal = gov_readiness_count($db, 'SELECT COUNT(*) AS c FROM `submission_jobs`');
        }
        $audit['queue_safety'] = [
            'submission_jobs_total' => $jobsTotal,
            'clean_queue' => $jobsTotal === 0,
        ];
        if ($jobsTotal > 0) {
            $warnings[] = 'Local submission_jobs table is not empty. Review queue before any future live workflow.';
            $cautions[] = 'local_submission_jobs_exist';
        }

        if (($audit['recent_bookings']['submission_safe_rows'] ?? 0) === 0) {
            $recommendations[] = 'No real current submission-safe Bolt candidate was found. This is expected until a real Bolt ride exists at least ' . $guardMinutes . ' minutes in the future.';
        } else {
            $recommendations[] = 'A submission-safe candidate exists in preflight. Keep live EDXEIX disabled until you explicitly approve a controlled live submit.';
        }
        if (($audit['reference_counts']['drivers']['mapped'] ?? 0) < 1 || ($audit['reference_counts']['vehicles']['mapped'] ?? 0) < 1) {
            $recommendations[] = 'Map at least one Bolt driver and one Bolt vehicle to EDXEIX before the real future Bolt test.';
        }

        if ($blocking) {
            $verdict = 'NOT_READY';
            $reason = 'One or more blocking safety/schema/config issues must be fixed first.';
        } elseif (($audit['reference_counts']['drivers']['mapped'] ?? 0) < 1 || ($audit['reference_counts']['vehicles']['mapped'] ?? 0) < 1) {
            $verdict = 'READY_FOR_PREFLIGHT_ONLY';
            $reason = 'Audit can run safely, but mapping coverage is not enough for a real future Bolt test.';
        } elseif ($cautions) {
            $verdict = 'READY_FOR_PREFLIGHT_ONLY';
            $reason = 'Core audit is safe, but cautions should be cleared before the controlled real future Bolt test.';
        } else {
            $verdict = 'READY_FOR_REAL_BOLT_FUTURE_TEST';
            $reason = 'Safe for the next real Bolt future-ride preflight test. This does not authorize live EDXEIX submission.';
        }

        $audit['ok'] = empty($blocking);
        $audit['warnings'] = array_values(array_unique($warnings));
        $audit['recommendations'] = array_values(array_unique($recommendations));
        $audit['blocking_issues'] = array_values(array_unique($blocking));
        $audit['cautions'] = array_values(array_unique($cautions));
        $audit['verdict'] = $verdict;
        $audit['verdict_reason'] = $reason;
        return $audit;
    }
}

if (gov_readiness_is_direct_request()) {
    try {
        $payload = gov_readiness_build_audit();
        gov_readiness_json_response($payload, empty($payload['blocking_issues']) ? 200 : 200);
    } catch (Throwable $e) {
        gov_readiness_json_response([
            'ok' => false,
            'script' => 'bolt_readiness_audit.php',
            'generated_at' => gov_readiness_now(),
            'read_only' => true,
            'verdict' => 'NOT_READY',
            'error' => $e->getMessage(),
            'note' => 'No live EDXEIX submission was attempted.',
        ], 500);
    }
}
