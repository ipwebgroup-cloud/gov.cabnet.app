<?php
/**
 * gov.cabnet.app Bolt Fleet Integration helpers
 *
 * Purpose:
 * - OAuth token flow for verified Bolt Fleet Integration API.
 * - Pull live drivers, vehicles, fleet orders.
 * - Upsert Bolt UUID/plate based mappings.
 * - Store raw payloads and normalized bookings without submitting to EDXEIX.
 *
 * Security:
 * - No secrets are stored in this file.
 * - Reads secrets from /home/cabnet/gov.cabnet.app_config/*.php, constants, or env vars.
 * - Keep this file outside the public web root.
 */

declare(strict_types=1);

if (!function_exists('gov_bridge_paths')) {
    function gov_bridge_paths(): array
    {
        $home = '/home/cabnet';
        return [
            'home'      => $home,
            'public'    => $home . '/public_html/gov.cabnet.app',
            'app'       => $home . '/gov.cabnet.app_app',
            'config'    => $home . '/gov.cabnet.app_config',
            'sql'       => $home . '/gov.cabnet.app_sql',
            'storage'   => $home . '/gov.cabnet.app_app/storage',
            'runtime'   => $home . '/gov.cabnet.app_app/storage/runtime',
            'logs'      => $home . '/gov.cabnet.app_app/storage/logs',
            'artifacts' => $home . '/gov.cabnet.app_app/storage/artifacts',
        ];
    }
}

if (!function_exists('gov_bridge_ensure_dirs')) {
    function gov_bridge_ensure_dirs(): void
    {
        foreach (['storage', 'runtime', 'logs', 'artifacts'] as $key) {
            $dir = gov_bridge_paths()[$key];
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
        }
    }
}

if (!function_exists('gov_bridge_json_response')) {
    function gov_bridge_json_response(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent() && PHP_SAPI !== 'cli') {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Robots-Tag: noindex, nofollow', true);
        }
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

if (!function_exists('gov_bridge_is_cli')) {
    function gov_bridge_is_cli(): bool
    {
        return PHP_SAPI === 'cli';
    }
}

if (!function_exists('gov_bridge_request_param')) {
    function gov_bridge_request_param(string $key, $default = null)
    {
        if (gov_bridge_is_cli()) {
            global $argv;
            $argv = is_array($argv ?? null) ? $argv : [];
            foreach ($argv as $arg) {
                if ($arg === '--' . $key) {
                    return true;
                }
                if (strpos($arg, '--' . $key . '=') === 0) {
                    return substr($arg, strlen('--' . $key . '='));
                }
            }
            return $default;
        }

        return $_GET[$key] ?? $_POST[$key] ?? $default;
    }
}

if (!function_exists('gov_bridge_bool_param')) {
    function gov_bridge_bool_param(string $key, bool $default = false): bool
    {
        $value = gov_bridge_request_param($key, $default ? '1' : '0');
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gov_bridge_int_param')) {
    function gov_bridge_int_param(string $key, int $default, int $min = 0, int $max = 1000000): int
    {
        $raw = gov_bridge_request_param($key, (string)$default);
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
        return max($min, min($max, (int)$value));
    }
}

if (!function_exists('gov_bridge_load_config')) {
    function gov_bridge_load_config(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $paths = gov_bridge_paths();
        $merged = [];
        $files = [
            $paths['config'] . '/config.php',
            $paths['config'] . '/database.php',
            $paths['config'] . '/app.php',
            $paths['config'] . '/bolt.php',
            $paths['config'] . '/edxeix.php',
        ];

        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $included = (static function (string $file): array {
                $config = null;
                $database = null;
                $db = null;
                $bolt = null;
                $edxeix = null;
                $app = null;
                $returned = require $file;
                return [
                    'returned' => $returned,
                    'config' => $config,
                    'database' => $database,
                    'db' => $db,
                    'bolt' => $bolt,
                    'edxeix' => $edxeix,
                    'app' => $app,
                ];
            })($file);

            if (is_array($included['returned'])) {
                $merged = array_replace_recursive($merged, $included['returned']);
            }
            if (is_array($included['config'])) {
                $merged = array_replace_recursive($merged, $included['config']);
            }
            if (is_array($included['database'])) {
                $merged['database'] = array_replace_recursive($merged['database'] ?? [], $included['database']);
            }
            if (is_array($included['db'])) {
                $merged['database'] = array_replace_recursive($merged['database'] ?? [], $included['db']);
            }
            if (is_array($included['bolt'])) {
                $merged['bolt'] = array_replace_recursive($merged['bolt'] ?? [], $included['bolt']);
            }
            if (is_array($included['edxeix'])) {
                $merged['edxeix'] = array_replace_recursive($merged['edxeix'] ?? [], $included['edxeix']);
            }
            if (is_array($included['app'])) {
                $merged['app'] = array_replace_recursive($merged['app'] ?? [], $included['app']);
            }
        }

        $constantMap = [
            'DB_HOST' => ['database', 'host'],
            'DB_NAME' => ['database', 'database'],
            'DB_DATABASE' => ['database', 'database'],
            'DB_USER' => ['database', 'username'],
            'DB_USERNAME' => ['database', 'username'],
            'DB_PASS' => ['database', 'password'],
            'DB_PASSWORD' => ['database', 'password'],
            'BOLT_CLIENT_ID' => ['bolt', 'client_id'],
            'BOLT_CLIENT_SECRET' => ['bolt', 'client_secret'],
            'BOLT_COMPANY_ID' => ['bolt', 'company_id'],
            'BOLT_COMPANY_IDS' => ['bolt', 'company_ids'],
            'BOLT_TOKEN_AUTH_MODE' => ['bolt', 'token_auth_mode'],
        ];

        foreach ($constantMap as $constant => $target) {
            if (defined($constant)) {
                $merged[$target[0]][$target[1]] = constant($constant);
            }
            $env = getenv($constant);
            if ($env !== false && $env !== '') {
                $merged[$target[0]][$target[1]] = $env;
            }
        }

        $merged['bolt']['token_url'] = $merged['bolt']['token_url'] ?? 'https://oidc.bolt.eu/token';
        $merged['bolt']['api_base'] = rtrim($merged['bolt']['api_base'] ?? 'https://node.bolt.eu/fleet-integration-gateway', '/');
        $merged['bolt']['scope'] = $merged['bolt']['scope'] ?? 'fleet-integration:api';
        $merged['bolt']['company_id'] = (int)($merged['bolt']['company_id'] ?? 297837);
        $merged['bolt']['limit'] = (int)($merged['bolt']['limit'] ?? 100);
        $merged['bolt']['token_auth_mode'] = $merged['bolt']['token_auth_mode'] ?? 'basic';

        $merged['edxeix']['lessor_id'] = (int)($merged['edxeix']['lessor_id'] ?? 3814);
        $merged['edxeix']['default_starting_point_id'] = (int)($merged['edxeix']['default_starting_point_id'] ?? 5875309);
        $merged['edxeix']['future_start_guard_minutes'] = (int)($merged['edxeix']['future_start_guard_minutes'] ?? 30);

        $cached = $merged;
        return $cached;
    }
}

if (!function_exists('gov_bridge_required_config_value')) {
    function gov_bridge_required_config_value(array $config, array $path, string $label): string
    {
        $value = $config;
        foreach ($path as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                throw new RuntimeException('Missing config value: ' . $label);
            }
            $value = $value[$part];
        }
        $value = trim((string)$value);
        if ($value === '' || stripos($value, 'REPLACE_') === 0) {
            throw new RuntimeException('Missing or placeholder config value: ' . $label);
        }
        return $value;
    }
}

if (!function_exists('gov_bridge_db')) {
    function gov_bridge_db(): mysqli
    {
        static $db = null;
        if ($db instanceof mysqli) {
            return $db;
        }

        $config = gov_bridge_load_config();
        $database = $config['database'] ?? [];
        $host = (string)($database['host'] ?? getenv('DB_HOST') ?: 'localhost');
        $name = (string)($database['database'] ?? $database['name'] ?? getenv('DB_DATABASE') ?: 'cabnet_gov');
        $user = (string)($database['username'] ?? $database['user'] ?? getenv('DB_USERNAME') ?: '');
        $pass = (string)($database['password'] ?? $database['pass'] ?? getenv('DB_PASSWORD') ?: '');
        $port = (int)($database['port'] ?? 3306);

        if ($user === '') {
            throw new RuntimeException('Database username is missing from external config.');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli($host, $user, $pass, $name, $port);
        $db->set_charset('utf8mb4');
        return $db;
    }
}

if (!function_exists('gov_bridge_quote_identifier')) {
    function gov_bridge_quote_identifier(string $identifier): string
    {
        // SQL identifiers cannot be placeholders. Restrict to safe table/column names.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Unsafe SQL identifier: ' . $identifier);
        }
        return '`' . $identifier . '`';
    }
}

if (!function_exists('gov_bridge_bind_params')) {
    function gov_bridge_bind_params(mysqli_stmt $stmt, string $types, array $values): void
    {
        if (!$values) {
            return;
        }
        $refs = [];
        foreach ($values as $key => $value) {
            if ($value === null) {
                $values[$key] = null;
            } elseif (is_bool($value)) {
                $values[$key] = $value ? '1' : '0';
            } else {
                $values[$key] = (string)$value;
            }
            $refs[$key] = &$values[$key];
        }
        $stmt->bind_param($types, ...$refs);
    }
}

if (!function_exists('gov_bridge_table_exists')) {
    function gov_bridge_table_exists(mysqli $db, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        // Avoid SHOW TABLES LIKE ? because some MariaDB/cPanel builds reject
        // placeholders in SHOW statements and report a syntax error near '?'.
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        gov_bridge_bind_params($stmt, 's', [$table]);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $cache[$table] = ((int)($row['c'] ?? 0)) > 0;
        return $cache[$table];
    }
}

if (!function_exists('gov_bridge_table_columns')) {
    function gov_bridge_table_columns(mysqli $db, string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        if (!gov_bridge_table_exists($db, $table)) {
            $cache[$table] = [];
            return [];
        }
        $result = $db->query('SHOW COLUMNS FROM ' . gov_bridge_quote_identifier($table));
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }
        $cache[$table] = $columns;
        return $columns;
    }
}

if (!function_exists('gov_bridge_clear_table_columns_cache')) {
    function gov_bridge_clear_table_columns_cache(): void
    {
        // Function intentionally left as extension point for older deployments.
        // Current process is short-lived, so column cache reset is not necessary.
    }
}

if (!function_exists('gov_bridge_filter_row')) {
    function gov_bridge_filter_row(mysqli $db, string $table, array $row): array
    {
        $columns = gov_bridge_table_columns($db, $table);
        if (!$columns) {
            return [];
        }
        $row = array_intersect_key($row, $columns);
        return gov_bridge_normalize_row_values_for_columns($row, $columns);
    }
}

if (!function_exists('gov_bridge_column_is_nullable')) {
    function gov_bridge_column_is_nullable(array $column): bool
    {
        return strtoupper((string)($column['Null'] ?? '')) === 'YES';
    }
}

if (!function_exists('gov_bridge_column_has_default')) {
    function gov_bridge_column_has_default(array $column): bool
    {
        return array_key_exists('Default', $column) && $column['Default'] !== null;
    }
}

if (!function_exists('gov_bridge_column_is_auto_increment')) {
    function gov_bridge_column_is_auto_increment(array $column): bool
    {
        return stripos((string)($column['Extra'] ?? ''), 'auto_increment') !== false;
    }
}

if (!function_exists('gov_bridge_column_is_numeric')) {
    function gov_bridge_column_is_numeric(array $column): bool
    {
        $type = strtolower((string)($column['Type'] ?? ''));
        foreach (['int', 'decimal', 'numeric', 'float', 'double', 'real'] as $needle) {
            if (strpos($type, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('gov_bridge_normalize_row_values_for_columns')) {
    function gov_bridge_normalize_row_values_for_columns(array $row, array $columns): array
    {
        foreach ($row as $column => $value) {
            if (!isset($columns[$column])) {
                continue;
            }
            if (!gov_bridge_column_is_numeric($columns[$column])) {
                continue;
            }
            if ($value === '' || $value === false) {
                $row[$column] = gov_bridge_column_is_nullable($columns[$column]) ? null : '0';
                continue;
            }
            if (is_string($value)) {
                $clean = trim($value);
                $clean = str_replace([',', '€', '$', '£'], ['', '', '', ''], $clean);
                if ($clean === '') {
                    $row[$column] = gov_bridge_column_is_nullable($columns[$column]) ? null : '0';
                } elseif (is_numeric($clean)) {
                    $row[$column] = $clean;
                }
            }
        }
        return $row;
    }
}

if (!function_exists('gov_bridge_default_for_required_column')) {
    function gov_bridge_default_for_required_column(string $table, string $column, array $definition)
    {
        $now = date('Y-m-d H:i:s');
        $type = strtolower((string)($definition['Type'] ?? ''));

        if (in_array($column, ['source_system', 'source_type'], true)) {
            return 'bolt';
        }
        if (in_array($column, ['created_at', 'updated_at', 'last_seen_at', 'captured_at'], true)) {
            return $now;
        }
        if (in_array($column, ['edxeix_driver_id', 'edxeix_vehicle_id', 'edxeix_ready', 'is_scheduled', 'raw_payload_id'], true)) {
            return '0';
        }
        if (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
            return '0';
        }
        if (strpos($type, 'date') !== false || strpos($type, 'time') !== false || strpos($type, 'year') !== false) {
            return $now;
        }

        return '';
    }
}

if (!function_exists('gov_bridge_apply_insert_defaults')) {
    function gov_bridge_apply_insert_defaults(mysqli $db, string $table, array $row): array
    {
        $columns = gov_bridge_table_columns($db, $table);
        if (!$columns) {
            return [];
        }

        $row = array_intersect_key($row, $columns);
        $row = gov_bridge_normalize_row_values_for_columns($row, $columns);

        foreach ($columns as $column => $definition) {
            if (gov_bridge_column_is_auto_increment($definition)) {
                continue;
            }
            if (array_key_exists($column, $row)) {
                if (($row[$column] === '' || $row[$column] === false) && gov_bridge_column_is_numeric($definition)) {
                    $row[$column] = gov_bridge_column_is_nullable($definition) ? null : '0';
                }
                if ($row[$column] !== null) {
                    continue;
                }
            }
            if (gov_bridge_column_is_nullable($definition) || gov_bridge_column_has_default($definition)) {
                continue;
            }
            $row[$column] = gov_bridge_default_for_required_column($table, $column, $definition);
        }

        return $row;
    }
}

if (!function_exists('gov_bridge_insert_row')) {
    function gov_bridge_insert_row(mysqli $db, string $table, array $row): int
    {
        $row = gov_bridge_apply_insert_defaults($db, $table, $row);
        if (!$row) {
            throw new RuntimeException('No compatible columns available for insert into ' . $table);
        }
        $columns = array_keys($row);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . gov_bridge_quote_identifier($table) . ' (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')';
        $stmt = $db->prepare($sql);
        $types = str_repeat('s', count($columns));
        $values = array_map(static function ($v) {
            if ($v === null) {
                return null;
            }
            if (is_bool($v)) {
                return $v ? '1' : '0';
            }
            return (string)$v;
        }, array_values($row));
        gov_bridge_bind_params($stmt, $types, $values);
        $stmt->execute();
        return (int)$db->insert_id;
    }
}

if (!function_exists('gov_bridge_update_row')) {
    function gov_bridge_update_row(mysqli $db, string $table, array $row, string $whereSql, array $whereParams): int
    {
        $row = gov_bridge_filter_row($db, $table, $row);
        if (!$row) {
            return 0;
        }
        $sets = [];
        foreach (array_keys($row) as $column) {
            $sets[] = '`' . $column . '` = ?';
        }
        $sql = 'UPDATE ' . gov_bridge_quote_identifier($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . $whereSql;
        $stmt = $db->prepare($sql);
        $values = array_merge(array_map(static function ($v) {
            if ($v === null) {
                return null;
            }
            if (is_bool($v)) {
                return $v ? '1' : '0';
            }
            return (string)$v;
        }, array_values($row)), array_map('strval', $whereParams));
        $types = str_repeat('s', count($values));
        gov_bridge_bind_params($stmt, $types, $values);
        $stmt->execute();
        return $stmt->affected_rows;
    }
}

if (!function_exists('gov_bridge_fetch_one')) {
    function gov_bridge_fetch_one(mysqli $db, string $sql, array $params = []): ?array
    {
        $stmt = $db->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $values = array_map('strval', $params);
            gov_bridge_bind_params($stmt, $types, $values);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return $row ?: null;
    }
}

if (!function_exists('gov_bridge_fetch_all')) {
    function gov_bridge_fetch_all(mysqli $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $values = array_map('strval', $params);
            gov_bridge_bind_params($stmt, $types, $values);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('gov_bridge_json_encode_db')) {
    function gov_bridge_json_encode_db($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('gov_bridge_sha256')) {
    function gov_bridge_sha256($payload): string
    {
        return hash('sha256', gov_bridge_json_encode_db($payload));
    }
}

if (!function_exists('gov_bolt_raw_payload_dedupe_hash')) {
    function gov_bolt_raw_payload_dedupe_hash(string $endpoint, string $externalReference, array $payload): string
    {
        return gov_bridge_sha256([
            'table' => 'bolt_raw_payloads',
            'source_system' => 'bolt',
            'endpoint' => $endpoint,
            'external_reference' => $externalReference,
            'payload' => $payload,
        ]);
    }
}

if (!function_exists('gov_bolt_normalized_booking_dedupe_hash')) {
    function gov_bolt_normalized_booking_dedupe_hash(array $normalized): string
    {
        $external = (string)($normalized['external_order_id'] ?? $normalized['order_reference'] ?? $normalized['source_trip_reference'] ?? '');
        if ($external !== '') {
            return gov_bridge_sha256([
                'table' => 'normalized_bookings',
                'source_system' => (string)($normalized['source_system'] ?? 'bolt'),
                'external_order_id' => $external,
            ]);
        }

        return gov_bridge_sha256([
            'table' => 'normalized_bookings',
            'source_system' => (string)($normalized['source_system'] ?? 'bolt'),
            'driver_external_id' => (string)($normalized['driver_external_id'] ?? ''),
            'vehicle_external_id' => (string)($normalized['vehicle_external_id'] ?? ''),
            'started_at' => (string)($normalized['started_at'] ?? ''),
            'pickup_address' => (string)($normalized['pickup_address'] ?? ''),
            'destination_address' => (string)($normalized['destination_address'] ?? ''),
        ]);
    }
}

if (!function_exists('gov_bolt_prepare_normalized_booking_for_db')) {
    function gov_bolt_prepare_normalized_booking_for_db(mysqli $db, array $normalized): array
    {
        $columns = gov_bridge_table_columns($db, 'normalized_bookings');

        if (isset($columns['source_system']) && empty($normalized['source_system'])) {
            $normalized['source_system'] = 'bolt';
        }
        if (isset($columns['source_type']) && empty($normalized['source_type'])) {
            $normalized['source_type'] = 'bolt';
        }
        if (isset($columns['price']) && (!array_key_exists('price', $normalized) || $normalized['price'] === '' || $normalized['price'] === null)) {
            $normalized['price'] = '0.00';
        }

        /*
         * Some early starter schemas made ended_at NOT NULL. Bolt can return
         * cancelled/in-progress orders without a drop-off/finished timestamp.
         * If the live schema still requires a value, provide a safe fallback so
         * the importer does not fail. If the schema has been relaxed to NULL,
         * preserve the real Bolt absence as NULL.
         */
        $now = date('Y-m-d H:i:s');
        $createdFallback = $normalized['order_created_at'] ?? $normalized['created_at'] ?? $now;
        $startedFallback = $normalized['started_at'] ?? $createdFallback;
        $dateFallbacks = [
            'order_created_at' => $createdFallback,
            'drafted_at' => $createdFallback,
            'started_at' => $startedFallback,
            'ended_at' => $normalized['started_at'] ?? $createdFallback,
        ];
        foreach ($dateFallbacks as $dateColumn => $fallbackValue) {
            if (!isset($columns[$dateColumn])) {
                continue;
            }
            $missing = !array_key_exists($dateColumn, $normalized) || $normalized[$dateColumn] === '' || $normalized[$dateColumn] === null;
            if ($missing && !gov_bridge_column_is_nullable($columns[$dateColumn]) && !gov_bridge_column_has_default($columns[$dateColumn])) {
                $normalized[$dateColumn] = $fallbackValue ?: $now;
            }
        }

        if (isset($columns['dedupe_hash'])) {
            $normalized['dedupe_hash'] = gov_bolt_normalized_booking_dedupe_hash($normalized);
        }

        return $normalized;
    }
}

if (!function_exists('gov_bridge_http_request')) {
    function gov_bridge_http_request(string $method, string $url, array $headers = [], $body = null, int $timeout = 45): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required.');
        }

        $ch = curl_init($url);
        $headerList = [];
        foreach ($headers as $key => $value) {
            $headerList[] = is_string($key) ? ($key . ': ' . $value) : (string)$value;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerList,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException('HTTP request failed: ' . $curlError);
        }

        $decoded = null;
        $content = trim((string)$responseBody);
        if ($content !== '') {
            $decoded = json_decode($content, true);
        }

        return [
            'status' => $status,
            'body' => $responseBody,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }
}

if (!function_exists('gov_bolt_token')) {
    function gov_bolt_token(bool $forceRefresh = false): string
    {
        static $cached = null;
        if (!$forceRefresh && is_array($cached) && isset($cached['access_token'], $cached['expires_at']) && $cached['expires_at'] > time() + 60) {
            return $cached['access_token'];
        }

        $config = gov_bridge_load_config();
        $bolt = $config['bolt'] ?? [];
        $clientId = gov_bridge_required_config_value($config, ['bolt', 'client_id'], 'bolt.client_id');
        $clientSecret = gov_bridge_required_config_value($config, ['bolt', 'client_secret'], 'bolt.client_secret');
        $tokenUrl = (string)$bolt['token_url'];
        $scope = (string)$bolt['scope'];
        $authMode = (string)($bolt['token_auth_mode'] ?? 'basic');

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => $scope,
        ], '', '&');
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

        if ($authMode === 'body') {
            $body = http_build_query([
                'grant_type' => 'client_credentials',
                'scope' => $scope,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ], '', '&');
        } else {
            $headers['Authorization'] = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);
        }

        $response = gov_bridge_http_request('POST', $tokenUrl, $headers, $body);
        if ($response['status'] >= 400 || !is_array($response['json']) || empty($response['json']['access_token'])) {
            // Fallback once for deployments that previously worked with credentials in the body.
            if ($authMode !== 'body') {
                $bodyFallback = http_build_query([
                    'grant_type' => 'client_credentials',
                    'scope' => $scope,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ], '', '&');
                $response = gov_bridge_http_request('POST', $tokenUrl, ['Content-Type' => 'application/x-www-form-urlencoded'], $bodyFallback);
            }
        }

        if ($response['status'] >= 400 || !is_array($response['json']) || empty($response['json']['access_token'])) {
            throw new RuntimeException('Bolt token request failed with HTTP ' . $response['status']);
        }

        $expiresIn = (int)($response['json']['expires_in'] ?? 300);
        $cached = [
            'access_token' => (string)$response['json']['access_token'],
            'expires_at' => time() + max(60, $expiresIn),
        ];

        return $cached['access_token'];
    }
}

if (!function_exists('gov_bolt_api_post')) {
    function gov_bolt_api_post(string $path, array $payload): array
    {
        $config = gov_bridge_load_config();
        $url = rtrim((string)$config['bolt']['api_base'], '/') . '/' . ltrim($path, '/');
        $headers = [
            'Authorization' => 'Bearer ' . gov_bolt_token(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $response = gov_bridge_http_request('POST', $url, $headers, gov_bridge_json_encode_db($payload));
        if ($response['status'] === 401) {
            $headers['Authorization'] = 'Bearer ' . gov_bolt_token(true);
            $response = gov_bridge_http_request('POST', $url, $headers, gov_bridge_json_encode_db($payload));
        }
        if ($response['status'] >= 400) {
            throw new RuntimeException('Bolt POST ' . $path . ' failed with HTTP ' . $response['status'] . ': ' . substr((string)$response['body'], 0, 500));
        }
        return $response['json'] ?? [];
    }
}

if (!function_exists('gov_bolt_api_get')) {
    function gov_bolt_api_get(string $path): array
    {
        $config = gov_bridge_load_config();
        $url = rtrim((string)$config['bolt']['api_base'], '/') . '/' . ltrim($path, '/');
        $headers = [
            'Authorization' => 'Bearer ' . gov_bolt_token(),
            'Accept' => 'application/json',
        ];
        $response = gov_bridge_http_request('GET', $url, $headers);
        if ($response['status'] === 401) {
            $headers['Authorization'] = 'Bearer ' . gov_bolt_token(true);
            $response = gov_bridge_http_request('GET', $url, $headers);
        }
        if ($response['status'] >= 400) {
            throw new RuntimeException('Bolt GET ' . $path . ' failed with HTTP ' . $response['status'] . ': ' . substr((string)$response['body'], 0, 500));
        }
        return $response['json'] ?? [];
    }
}

if (!function_exists('gov_bolt_extract_items')) {
    function gov_bolt_extract_items(array $payload, array $preferredKeys): array
    {
        foreach ($preferredKeys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
            if (isset($payload['data'][$key]) && is_array($payload['data'][$key])) {
                return array_values($payload['data'][$key]);
            }
        }
        if (isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])) {
            return array_values($payload['data']);
        }
        if (array_is_list($payload)) {
            return array_values($payload);
        }
        return [];
    }
}

if (!function_exists('gov_bolt_pick')) {
    function gov_bolt_pick(array $payload, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return $payload[$key];
            }
        }
        return $default;
    }
}

if (!function_exists('gov_bolt_ts_range')) {
    function gov_bolt_ts_range(int $hoursBack = 24): array
    {
        $end = time();
        $start = $end - ($hoursBack * 3600);
        return [$start, $end];
    }
}

if (!function_exists('gov_bolt_paginated_post')) {
    function gov_bolt_paginated_post(string $path, array $basePayload, array $itemKeys, int $maxPages = 20): array
    {
        $config = gov_bridge_load_config();
        $limit = max(1, min(500, (int)($basePayload['limit'] ?? $config['bolt']['limit'] ?? 100)));
        $offset = max(0, (int)($basePayload['offset'] ?? 0));
        $items = [];
        $pages = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $payload = $basePayload;
            $payload['limit'] = $limit;
            $payload['offset'] = $offset;
            $json = gov_bolt_api_post($path, $payload);
            $pageItems = gov_bolt_extract_items($json, $itemKeys);
            $pages[] = [
                'offset' => $offset,
                'count' => count($pageItems),
                'keys' => array_keys($json),
            ];
            foreach ($pageItems as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
            if (count($pageItems) < $limit) {
                break;
            }
            $offset += $limit;
        }

        return ['items' => $items, 'pages' => $pages];
    }
}

if (!function_exists('gov_bolt_get_companies')) {
    function gov_bolt_get_companies(): array
    {
        $json = gov_bolt_api_get('/fleetintegration/v1/getCompanies');
        return gov_bolt_extract_items($json, ['companies', 'items', 'results']);
    }
}

if (!function_exists('gov_bolt_get_drivers')) {
    function gov_bolt_get_drivers(int $hoursBack = 720): array
    {
        $config = gov_bridge_load_config();
        [$startTs, $endTs] = gov_bolt_ts_range($hoursBack);
        return gov_bolt_paginated_post('/fleetintegration/v1/getDrivers', [
            'company_id' => (int)$config['bolt']['company_id'],
            'start_ts' => $startTs,
            'end_ts' => $endTs,
            'limit' => (int)$config['bolt']['limit'],
            'offset' => 0,
        ], ['drivers', 'items', 'results']);
    }
}

if (!function_exists('gov_bolt_get_vehicles')) {
    function gov_bolt_get_vehicles(int $hoursBack = 720): array
    {
        $config = gov_bridge_load_config();
        [$startTs, $endTs] = gov_bolt_ts_range($hoursBack);
        return gov_bolt_paginated_post('/fleetintegration/v1/getVehicles', [
            'company_id' => (int)$config['bolt']['company_id'],
            'start_ts' => $startTs,
            'end_ts' => $endTs,
            'limit' => (int)$config['bolt']['limit'],
            'offset' => 0,
        ], ['vehicles', 'items', 'results']);
    }
}

if (!function_exists('gov_bolt_get_fleet_orders')) {
    function gov_bolt_get_fleet_orders(int $hoursBack = 24): array
    {
        $config = gov_bridge_load_config();
        [$startTs, $endTs] = gov_bolt_ts_range($hoursBack);
        $companyIds = $config['bolt']['company_ids'] ?? [(int)$config['bolt']['company_id']];
        if (is_string($companyIds)) {
            $companyIds = array_values(array_filter(array_map('intval', explode(',', $companyIds))));
        }
        return gov_bolt_paginated_post('/fleetintegration/v1/getFleetOrders', [
            'company_ids' => array_map('intval', (array)$companyIds),
            'start_ts' => $startTs,
            'end_ts' => $endTs,
            'limit' => (int)$config['bolt']['limit'],
            'offset' => 0,
        ], ['orders', 'fleet_orders', 'items', 'results']);
    }
}

if (!function_exists('gov_bolt_normalize_driver')) {
    function gov_bolt_normalize_driver(array $driver): array
    {
        $uuid = (string)gov_bolt_pick($driver, ['driver_uuid', 'uuid', 'id', 'driver_id'], '');
        $name = (string)gov_bolt_pick($driver, ['driver_name', 'full_name', 'name'], '');
        $phone = (string)gov_bolt_pick($driver, ['driver_phone', 'phone'], '');
        $plate = '';
        $activeVehicleUuid = '';
        $activeVehicle = gov_bolt_pick($driver, ['active_vehicle', 'vehicle'], []);
        if (is_array($activeVehicle)) {
            $plate = (string)gov_bolt_pick($activeVehicle, ['reg_number', 'vehicle_license_plate', 'license_plate', 'plate'], '');
            $activeVehicleUuid = (string)gov_bolt_pick($activeVehicle, ['uuid', 'vehicle_uuid', 'id'], '');
        }
        $plate = $plate ?: (string)gov_bolt_pick($driver, ['vehicle_license_plate', 'reg_number', 'plate'], '');
        $activeVehicleUuid = $activeVehicleUuid ?: (string)gov_bolt_pick($driver, ['vehicle_uuid', 'active_vehicle_uuid'], '');

        return [
            'source_system' => 'bolt',
            'source_type' => 'bolt',
            'external_driver_id' => $uuid,
            'external_id' => $uuid,
            'driver_uuid' => $uuid,
            'external_driver_name' => $name,
            'driver_phone' => $phone,
            'active_vehicle_uuid' => $activeVehicleUuid,
            'active_vehicle_plate' => $plate,
            'raw_payload_json' => gov_bridge_json_encode_db($driver),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('gov_bolt_normalize_vehicle')) {
    function gov_bolt_normalize_vehicle(array $vehicle): array
    {
        $uuid = (string)gov_bolt_pick($vehicle, ['vehicle_uuid', 'uuid', 'id', 'vehicle_id'], '');
        $plate = (string)gov_bolt_pick($vehicle, ['reg_number', 'vehicle_license_plate', 'license_plate', 'plate'], '');
        $model = (string)gov_bolt_pick($vehicle, ['vehicle_model', 'model', 'make_model', 'name'], '');
        return [
            'source_system' => 'bolt',
            'source_type' => 'bolt',
            'external_vehicle_id' => $uuid,
            'external_id' => $uuid,
            'vehicle_uuid' => $uuid,
            'plate' => strtoupper(trim($plate)),
            'external_vehicle_name' => $model,
            'vehicle_model' => $model,
            'raw_payload_json' => gov_bridge_json_encode_db($vehicle),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('gov_upsert_driver_mapping')) {
    function gov_upsert_driver_mapping(mysqli $db, array $normalized, bool $dryRun = false): array
    {
        if (empty($normalized['external_driver_id'])) {
            return ['ok' => false, 'action' => 'skipped', 'reason' => 'missing external_driver_id'];
        }
        $existing = gov_bridge_fetch_one(
            $db,
            'SELECT * FROM mapping_drivers WHERE source_system = ? AND external_driver_id = ? LIMIT 1',
            ['bolt', $normalized['external_driver_id']]
        );
        if ($dryRun) {
            return ['ok' => true, 'action' => $existing ? 'would_update' : 'would_insert', 'external_driver_id' => $normalized['external_driver_id']];
        }
        if ($existing) {
            $row = $normalized;
            unset($row['created_at']);
            // Never overwrite a manually saved EDXEIX driver id with blank data.
            unset($row['edxeix_driver_id']);
            gov_bridge_update_row($db, 'mapping_drivers', $row, 'source_system = ? AND external_driver_id = ?', ['bolt', $normalized['external_driver_id']]);
            return ['ok' => true, 'action' => 'updated', 'id' => $existing['id'] ?? null, 'external_driver_id' => $normalized['external_driver_id']];
        }
        $id = gov_bridge_insert_row($db, 'mapping_drivers', $normalized);
        return ['ok' => true, 'action' => 'inserted', 'id' => $id, 'external_driver_id' => $normalized['external_driver_id']];
    }
}

if (!function_exists('gov_upsert_vehicle_mapping')) {
    function gov_upsert_vehicle_mapping(mysqli $db, array $normalized, bool $dryRun = false): array
    {
        if (empty($normalized['external_vehicle_id']) && empty($normalized['plate'])) {
            return ['ok' => false, 'action' => 'skipped', 'reason' => 'missing external_vehicle_id and plate'];
        }

        $existing = null;
        if (!empty($normalized['external_vehicle_id'])) {
            $existing = gov_bridge_fetch_one(
                $db,
                'SELECT * FROM mapping_vehicles WHERE source_system = ? AND external_vehicle_id = ? LIMIT 1',
                ['bolt', $normalized['external_vehicle_id']]
            );
        }
        if (!$existing && !empty($normalized['plate'])) {
            $existing = gov_bridge_fetch_one(
                $db,
                'SELECT * FROM mapping_vehicles WHERE source_system = ? AND plate = ? LIMIT 1',
                ['bolt', $normalized['plate']]
            );
        }

        if ($dryRun) {
            return ['ok' => true, 'action' => $existing ? 'would_update' : 'would_insert', 'external_vehicle_id' => $normalized['external_vehicle_id'] ?? '', 'plate' => $normalized['plate'] ?? ''];
        }
        if ($existing) {
            $row = $normalized;
            unset($row['created_at']);
            // Never overwrite a manually saved EDXEIX vehicle id with blank data.
            unset($row['edxeix_vehicle_id']);
            if (!empty($existing['external_vehicle_id'])) {
                gov_bridge_update_row($db, 'mapping_vehicles', $row, 'source_system = ? AND external_vehicle_id = ?', ['bolt', $existing['external_vehicle_id']]);
            } else {
                gov_bridge_update_row($db, 'mapping_vehicles', $row, 'source_system = ? AND plate = ?', ['bolt', $existing['plate']]);
            }
            return ['ok' => true, 'action' => 'updated', 'id' => $existing['id'] ?? null, 'external_vehicle_id' => $normalized['external_vehicle_id'] ?? '', 'plate' => $normalized['plate'] ?? ''];
        }
        $id = gov_bridge_insert_row($db, 'mapping_vehicles', $normalized);
        return ['ok' => true, 'action' => 'inserted', 'id' => $id, 'external_vehicle_id' => $normalized['external_vehicle_id'] ?? '', 'plate' => $normalized['plate'] ?? ''];
    }
}

if (!function_exists('gov_store_raw_payload')) {
    function gov_store_raw_payload(mysqli $db, string $endpoint, string $externalReference, array $payload, bool $dryRun = false): ?int
    {
        $hash = gov_bolt_raw_payload_dedupe_hash($endpoint, $externalReference, $payload);
        if ($dryRun) {
            return null;
        }

        $columns = gov_bridge_table_columns($db, 'bolt_raw_payloads');
        $existing = null;

        if (isset($columns['payload_hash'])) {
            $existing = gov_bridge_fetch_one($db, 'SELECT * FROM bolt_raw_payloads WHERE payload_hash = ? LIMIT 1', [$hash]);
        }
        if (!$existing && isset($columns['dedupe_hash'])) {
            $existing = gov_bridge_fetch_one($db, 'SELECT * FROM bolt_raw_payloads WHERE dedupe_hash = ? LIMIT 1', [$hash]);
        }
        if (!$existing && isset($columns['external_reference']) && isset($columns['source_system'])) {
            $existing = gov_bridge_fetch_one($db, 'SELECT * FROM bolt_raw_payloads WHERE source_system = ? AND external_reference = ? LIMIT 1', ['bolt', $externalReference]);
        }
        if (!$existing && isset($columns['external_reference']) && !isset($columns['source_system'])) {
            $existing = gov_bridge_fetch_one($db, 'SELECT * FROM bolt_raw_payloads WHERE external_reference = ? LIMIT 1', [$externalReference]);
        }

        if ($existing) {
            foreach (['id', 'raw_payload_id', 'payload_id'] as $idColumn) {
                if (isset($existing[$idColumn])) {
                    return (int)$existing[$idColumn];
                }
            }
            return null;
        }

        return gov_bridge_insert_row($db, 'bolt_raw_payloads', [
            'source_system' => 'bolt',
            'source_type' => 'bolt',
            'source_endpoint' => $endpoint,
            'external_reference' => $externalReference,
            'payload_hash' => $hash,
            'dedupe_hash' => $hash,
            'payload_json' => gov_bridge_json_encode_db($payload),
            'raw_json' => gov_bridge_json_encode_db($payload),
            'captured_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

if (!function_exists('gov_bolt_datetime_from_ts')) {
    function gov_bolt_datetime_from_ts($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $ts = (int)$value;
            if ($ts > 20000000000) {
                $ts = (int)floor($ts / 1000);
            }
            return date('Y-m-d H:i:s', $ts);
        }
        $ts = strtotime((string)$value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}

if (!function_exists('gov_bolt_decimal_from_money_value')) {
    function gov_bolt_decimal_from_money_value($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return number_format((float)$value, 2, '.', '');
        }
        if (is_string($value)) {
            $clean = trim($value);
            if ($clean === '') {
                return null;
            }
            $clean = str_replace([',', '€', '$', '£'], ['', '', '', ''], $clean);
            $clean = preg_replace('/[^0-9\.\-]/', '', $clean);
            if ($clean !== '' && is_numeric($clean)) {
                return number_format((float)$clean, 2, '.', '');
            }
        }
        return null;
    }
}

if (!function_exists('gov_bolt_extract_price')) {
    function gov_bolt_extract_price($priceRaw): string
    {
        $direct = gov_bolt_decimal_from_money_value($priceRaw);
        if ($direct !== null) {
            return $direct;
        }

        if (is_array($priceRaw)) {
            foreach (['amount', 'value', 'price', 'total', 'total_amount', 'gross_amount', 'final_amount', 'ride_price', 'booking_price'] as $key) {
                if (array_key_exists($key, $priceRaw)) {
                    $value = gov_bolt_decimal_from_money_value($priceRaw[$key]);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }
            foreach ($priceRaw as $value) {
                if (is_array($value)) {
                    $nested = gov_bolt_extract_price($value);
                    if ($nested !== '0.00') {
                        return $nested;
                    }
                } else {
                    $decimal = gov_bolt_decimal_from_money_value($value);
                    if ($decimal !== null) {
                        return $decimal;
                    }
                }
            }
        }

        return '0.00';
    }
}
if (!function_exists('gov_normalize_bolt_order')) {
    function gov_normalize_bolt_order(array $order, ?int $rawPayloadId = null): array
    {
        $reference = (string)gov_bolt_pick($order, ['order_reference', 'reference', 'order_id', 'id'], '');
        $driverUuid = (string)gov_bolt_pick($order, ['driver_uuid', 'driver_id'], '');
        $driverName = (string)gov_bolt_pick($order, ['driver_name'], '');
        $driverObj = gov_bolt_pick($order, ['driver'], []);
        if (is_array($driverObj)) {
            $driverUuid = $driverUuid ?: (string)gov_bolt_pick($driverObj, ['uuid', 'driver_uuid', 'id'], '');
            $driverName = $driverName ?: (string)gov_bolt_pick($driverObj, ['driver_name', 'full_name', 'name'], '');
        }
        $vehicleUuid = (string)gov_bolt_pick($order, ['vehicle_uuid', 'vehicle_id'], '');
        $vehiclePlate = (string)gov_bolt_pick($order, ['vehicle_license_plate', 'reg_number', 'plate'], '');
        $vehicleModel = (string)gov_bolt_pick($order, ['vehicle_model'], '');
        $vehicleObj = gov_bolt_pick($order, ['vehicle'], []);
        if (is_array($vehicleObj)) {
            $vehicleUuid = $vehicleUuid ?: (string)gov_bolt_pick($vehicleObj, ['uuid', 'vehicle_uuid', 'id'], '');
            $vehiclePlate = $vehiclePlate ?: (string)gov_bolt_pick($vehicleObj, ['reg_number', 'vehicle_license_plate', 'license_plate', 'plate'], '');
            $vehicleModel = $vehicleModel ?: (string)gov_bolt_pick($vehicleObj, ['vehicle_model', 'model', 'make_model', 'name'], '');
        }
        $status = (string)gov_bolt_pick($order, ['order_status', 'status'], '');
        $pickup = (string)gov_bolt_pick($order, ['pickup_address', 'boarding_point', 'origin_address'], '');
        $destination = (string)gov_bolt_pick($order, ['destination_address', 'disembark_point', 'dropoff_address'], '');
        $priceRaw = gov_bolt_pick($order, ['order_price', 'price', 'total_price'], '');
        $price = gov_bolt_extract_price($priceRaw);

        $start = gov_bolt_datetime_from_ts(gov_bolt_pick($order, ['order_pickup_timestamp', 'order_accepted_timestamp', 'order_created_timestamp'], null));
        $end = gov_bolt_datetime_from_ts(gov_bolt_pick($order, ['order_drop_off_timestamp', 'order_finished_timestamp', 'order_cancelled_timestamp'], null));
        $created = gov_bolt_datetime_from_ts(gov_bolt_pick($order, ['order_created_timestamp', 'created_at'], null));

        return [
            'source_system' => 'bolt',
            'source_type' => 'bolt',
            'external_order_id' => $reference,
            'external_reference' => $reference,
            'order_reference' => $reference,
            'source_trip_reference' => $reference,
            'driver_external_id' => $driverUuid,
            'driver_name' => $driverName,
            'driver_phone' => (string)gov_bolt_pick($order, ['driver_phone'], ''),
            'vehicle_external_id' => $vehicleUuid,
            'vehicle_plate' => strtoupper(trim($vehiclePlate)),
            'vehicle_model' => $vehicleModel,
            'passenger_name' => 'Bolt Passenger',
            'lessee_name' => 'Bolt Passenger',
            'pickup_address' => $pickup,
            'boarding_point' => $pickup,
            'destination_address' => $destination,
            'disembark_point' => $destination,
            'price' => $price,
            'status' => $status,
            'order_status' => $status,
            'is_scheduled' => !empty($order['is_scheduled']) ? '1' : '0',
            'started_at' => $start,
            'ended_at' => $end,
            'order_created_at' => $created,
            'raw_payload_id' => $rawPayloadId,
            'normalized_payload_json' => gov_bridge_json_encode_db($order),
            'raw_payload_json' => gov_bridge_json_encode_db($order),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('gov_build_edxeix_preview_payload')) {
    function gov_build_edxeix_preview_payload(mysqli $db, array $normalized): array
    {
        $config = gov_bridge_load_config();
        $driverMapping = null;
        $vehicleMapping = null;

        if (!empty($normalized['driver_external_id'])) {
            $driverMapping = gov_bridge_fetch_one($db, 'SELECT * FROM mapping_drivers WHERE source_system = ? AND external_driver_id = ? LIMIT 1', ['bolt', $normalized['driver_external_id']]);
        }
        if (!empty($normalized['vehicle_external_id'])) {
            $vehicleMapping = gov_bridge_fetch_one($db, 'SELECT * FROM mapping_vehicles WHERE source_system = ? AND external_vehicle_id = ? LIMIT 1', ['bolt', $normalized['vehicle_external_id']]);
        }
        if (!$vehicleMapping && !empty($normalized['vehicle_plate'])) {
            $vehicleMapping = gov_bridge_fetch_one($db, 'SELECT * FROM mapping_vehicles WHERE source_system = ? AND plate = ? LIMIT 1', ['bolt', $normalized['vehicle_plate']]);
        }

        $startedAt = $normalized['started_at'] ?? null;
        $endedAt = $normalized['ended_at'] ?? null;
        $draftedAt = date('Y-m-d H:i:s');

        return [
            '_token' => '[loaded from saved EDXEIX session only at submit time]',
            'broker' => 'Bolt',
            'lessor' => (string)$config['edxeix']['lessor_id'],
            'lessee' => [
                'type' => 'physical_person',
                'name' => $normalized['passenger_name'] ?? $normalized['lessee_name'] ?? 'Bolt Passenger',
                'vat_number' => '',
                'legal_representative' => '',
            ],
            'driver' => (string)($driverMapping['edxeix_driver_id'] ?? ''),
            'vehicle' => (string)($vehicleMapping['edxeix_vehicle_id'] ?? ''),
            'starting_point_id' => (string)$config['edxeix']['default_starting_point_id'],
            'boarding_point' => $normalized['boarding_point'] ?? $normalized['pickup_address'] ?? '',
            'coordinates' => '',
            'disembark_point' => $normalized['disembark_point'] ?? $normalized['destination_address'] ?? '',
            'drafted_at' => $draftedAt,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'price' => $normalized['price'] ?? '',
            '_mapping_status' => [
                'driver_mapped' => !empty($driverMapping['edxeix_driver_id']),
                'vehicle_mapped' => !empty($vehicleMapping['edxeix_vehicle_id']),
                'future_guard_minutes' => (int)$config['edxeix']['future_start_guard_minutes'],
                'passes_future_guard' => gov_edxeix_future_guard_passes($startedAt),
            ],
        ];
    }
}

if (!function_exists('gov_edxeix_future_guard_passes')) {
    function gov_edxeix_future_guard_passes(?string $startedAt): bool
    {
        if (!$startedAt) {
            return false;
        }
        $config = gov_bridge_load_config();
        $guardMinutes = (int)$config['edxeix']['future_start_guard_minutes'];
        $ts = strtotime($startedAt);
        return $ts !== false && $ts >= (time() + ($guardMinutes * 60));
    }
}

if (!function_exists('gov_upsert_normalized_booking')) {
    function gov_upsert_normalized_booking(mysqli $db, array $normalized, bool $dryRun = false): array
    {
        $normalized = gov_bolt_prepare_normalized_booking_for_db($db, $normalized);
        $externalId = (string)($normalized['external_order_id'] ?? $normalized['order_reference'] ?? '');
        if ($externalId === '') {
            return ['ok' => false, 'action' => 'skipped', 'reason' => 'missing external_order_id/order_reference'];
        }

        $existing = null;
        $columns = gov_bridge_table_columns($db, 'normalized_bookings');
        if (isset($columns['external_order_id'])) {
            $existing = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE source_system = ? AND external_order_id = ? LIMIT 1', ['bolt', $externalId]);
        }
        if (!$existing && isset($columns['order_reference'])) {
            $existing = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE source_system = ? AND order_reference = ? LIMIT 1', ['bolt', $externalId]);
        }
        if (!$existing && isset($columns['dedupe_hash']) && !empty($normalized['dedupe_hash'])) {
            $existing = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE dedupe_hash = ? LIMIT 1', [$normalized['dedupe_hash']]);
        }

        $edxeixPreview = gov_build_edxeix_preview_payload($db, $normalized);
        $normalized['edxeix_payload_json'] = gov_bridge_json_encode_db($edxeixPreview);
        $normalized['edxeix_ready'] = (!empty($edxeixPreview['_mapping_status']['driver_mapped']) && !empty($edxeixPreview['_mapping_status']['vehicle_mapped'])) ? '1' : '0';
        $normalized = gov_bolt_prepare_normalized_booking_for_db($db, $normalized);

        if ($dryRun) {
            return [
                'ok' => true,
                'action' => $existing ? 'would_update' : 'would_insert',
                'external_order_id' => $externalId,
                'dedupe_hash' => $normalized['dedupe_hash'] ?? null,
                'edxeix_ready' => $normalized['edxeix_ready'],
            ];
        }

        if ($existing) {
            $row = $normalized;
            unset($row['created_at']);
            if (isset($columns['external_order_id'])) {
                gov_bridge_update_row($db, 'normalized_bookings', $row, 'source_system = ? AND external_order_id = ?', ['bolt', $externalId]);
            } elseif (isset($columns['order_reference'])) {
                gov_bridge_update_row($db, 'normalized_bookings', $row, 'source_system = ? AND order_reference = ?', ['bolt', $externalId]);
            } else {
                gov_bridge_update_row($db, 'normalized_bookings', $row, 'dedupe_hash = ?', [$normalized['dedupe_hash']]);
            }
            return ['ok' => true, 'action' => 'updated', 'id' => $existing['id'] ?? null, 'external_order_id' => $externalId, 'edxeix_ready' => $normalized['edxeix_ready']];
        }

        $id = gov_bridge_insert_row($db, 'normalized_bookings', $normalized);
        return ['ok' => true, 'action' => 'inserted', 'id' => $id, 'external_order_id' => $externalId, 'dedupe_hash' => $normalized['dedupe_hash'] ?? null, 'edxeix_ready' => $normalized['edxeix_ready']];
    }
}

if (!function_exists('gov_bolt_sync_reference')) {
    function gov_bolt_sync_reference(int $hoursBack = 720, bool $dryRun = false): array
    {
        gov_bridge_ensure_dirs();
        $db = gov_bridge_db();
        $driversResult = gov_bolt_get_drivers($hoursBack);
        $vehiclesResult = gov_bolt_get_vehicles($hoursBack);

        $driverActions = [];
        foreach ($driversResult['items'] as $driver) {
            $driverActions[] = gov_upsert_driver_mapping($db, gov_bolt_normalize_driver($driver), $dryRun);
        }

        $vehicleActions = [];
        foreach ($vehiclesResult['items'] as $vehicle) {
            $vehicleActions[] = gov_upsert_vehicle_mapping($db, gov_bolt_normalize_vehicle($vehicle), $dryRun);
        }

        return [
            'ok' => true,
            'dry_run' => $dryRun,
            'drivers_seen' => count($driversResult['items']),
            'vehicles_seen' => count($vehiclesResult['items']),
            'drivers' => $driverActions,
            'vehicles' => $vehicleActions,
            'pagination' => [
                'drivers' => $driversResult['pages'],
                'vehicles' => $vehiclesResult['pages'],
            ],
        ];
    }
}

if (!function_exists('gov_bolt_sync_orders')) {
    function gov_bolt_sync_orders(int $hoursBack = 24, bool $dryRun = false): array
    {
        gov_bridge_ensure_dirs();
        $db = gov_bridge_db();
        $ordersResult = gov_bolt_get_fleet_orders($hoursBack);
        $actions = [];

        foreach ($ordersResult['items'] as $order) {
            $reference = (string)gov_bolt_pick($order, ['order_reference', 'reference', 'order_id', 'id'], '');
            $rawId = gov_store_raw_payload($db, 'getFleetOrders', $reference, $order, $dryRun);
            $normalized = gov_normalize_bolt_order($order, $rawId);
            $actions[] = gov_upsert_normalized_booking($db, $normalized, $dryRun);
        }

        return [
            'ok' => true,
            'dry_run' => $dryRun,
            'hours_back' => $hoursBack,
            'orders_seen' => count($ordersResult['items']),
            'orders' => $actions,
            'pagination' => $ordersResult['pages'],
        ];
    }
}

if (!function_exists('gov_recent_rows')) {
    function gov_recent_rows(mysqli $db, string $table, int $limit = 20): array
    {
        if (!gov_bridge_table_exists($db, $table)) {
            return [];
        }
        $columns = gov_bridge_table_columns($db, $table);
        $orderColumn = 'id';
        foreach (['updated_at', 'last_seen_at', 'created_at', 'captured_at', 'id'] as $candidate) {
            if (isset($columns[$candidate])) {
                $orderColumn = $candidate;
                break;
            }
        }
        $limit = max(1, min(100, $limit));
        $result = $db->query('SELECT * FROM `' . $table . '` ORDER BY `' . $orderColumn . '` DESC LIMIT ' . $limit);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}
