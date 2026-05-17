<?php
/**
 * gov.cabnet.app — EDXEIX submit diagnostic library v3.2.21
 *
 * Purpose:
 * - Keep EDXEIX automation progress on the ASAP track without enabling unattended live submit.
 * - Analyze an explicitly selected normalized booking through the existing live-submit gate.
 * - Optionally perform one gated diagnostic HTTP POST and follow the redirect chain.
 * - Classify the final page/signals so HTTP 302 is no longer treated as proof by itself.
 *
 * Safety contract:
 * - Default mode is dry-run / analysis only.
 * - No transport is performed unless caller passes transport=1, exact confirmation phrase,
 *   and server-only live_submit.php gates already allow the selected booking.
 * - No secrets, cookies, CSRF tokens, raw HTML, or request payload values are printed.
 * - No DB writes are performed by this diagnostic library.
 * - Historical, cancelled, terminal, expired, invalid, lab/test, receipt-only, or past bookings remain blocked by the existing live gate.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

if (!function_exists('gov_edxdiag_now')) {
    function gov_edxdiag_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('gov_edxdiag_cli_value')) {
    function gov_edxdiag_cli_value(array $argv, string $key, ?string $default = null): ?string
    {
        foreach ($argv as $arg) {
            if ($arg === '--' . $key) {
                return '1';
            }
            if (strpos($arg, '--' . $key . '=') === 0) {
                return substr($arg, strlen('--' . $key . '='));
            }
        }
        return $default;
    }
}

if (!function_exists('gov_edxdiag_bool')) {
    function gov_edxdiag_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}


if (!function_exists('gov_edxdiag_lower')) {
    function gov_edxdiag_lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('gov_edxdiag_substr')) {
    function gov_edxdiag_substr(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
    }
}

if (!function_exists('gov_edxdiag_strpos')) {
    function gov_edxdiag_strpos(string $haystack, string $needle): int|false
    {
        return function_exists('mb_strpos') ? mb_strpos($haystack, $needle, 0, 'UTF-8') : strpos($haystack, $needle);
    }
}

if (!function_exists('gov_edxdiag_safe_url')) {
    function gov_edxdiag_safe_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '[configured-url]';
        }
        $scheme = (string)($parts['scheme'] ?? 'https');
        $host = (string)$parts['host'];
        $path = (string)($parts['path'] ?? '');
        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('gov_edxdiag_resolve_url')) {
    function gov_edxdiag_resolve_url(string $baseUrl, string $location): string
    {
        $location = trim($location);
        if ($location === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['host'])) {
            return $location;
        }
        $scheme = (string)($base['scheme'] ?? 'https');
        $host = (string)$base['host'];
        $port = isset($base['port']) ? ':' . (string)$base['port'] : '';
        if (strpos($location, '//') === 0) {
            return $scheme . ':' . $location;
        }
        if (strpos($location, '/') === 0) {
            return $scheme . '://' . $host . $port . $location;
        }
        $path = (string)($base['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        if ($dir === '' || $dir === '.') {
            $dir = '';
        }
        return $scheme . '://' . $host . $port . $dir . '/' . $location;
    }
}

if (!function_exists('gov_edxdiag_parse_response_headers')) {
    function gov_edxdiag_parse_response_headers(string $headersRaw): array
    {
        $headers = [];
        foreach (preg_split("/\r\n|\n|\r/", trim($headersRaw)) ?: [] as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            $value = trim($value);
            if ($name === '') {
                continue;
            }
            $headers[$name][] = $value;
        }
        return $headers;
    }
}

if (!function_exists('gov_edxdiag_header_first')) {
    function gov_edxdiag_header_first(array $headers, string $name): string
    {
        $key = strtolower($name);
        $values = $headers[$key] ?? [];
        return is_array($values) && isset($values[0]) ? (string)$values[0] : '';
    }
}

if (!function_exists('gov_edxdiag_html_title')) {
    function gov_edxdiag_html_title(string $body): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
            $title = trim(strip_tags(html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            return gov_edxdiag_substr($title, 0, 160);
        }
        return '';
    }
}

if (!function_exists('gov_edxdiag_body_fingerprint')) {
    function gov_edxdiag_body_fingerprint(string $body): array
    {
        $lower = gov_edxdiag_lower($body);
        $title = gov_edxdiag_html_title($body);
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        $excerpt = $text !== '' ? gov_edxdiag_substr($text, 0, 240) : '';

        $signals = [
            'login' => [
                'login', 'σύνδεση', 'εισοδοσ', 'είσοδος', 'password', 'κωδικός', 'username', 'email',
            ],
            'csrf' => [
                'csrf', 'xsrf', 'token mismatch', 'page expired', '419', 'expired', 'λήξη', 'έληξε',
            ],
            'validation_error' => [
                'validation', 'required', 'invalid', 'error', 'σφάλμα', 'λαθοσ', 'λάθος', 'υποχρεω', 'μη έγκυρ',
            ],
            'success_candidate' => [
                'success', 'saved', 'submitted', 'created', 'καταχωρ', 'αποθηκευ', 'επιτυχ', 'συμβάσεις ενοικίασης', 'lease agreement',
            ],
            'contract_list' => [
                'συμβάσεις ενοικίασης', 'lease agreement', 'agreement list', 'contracts', 'dashboard',
            ],
        ];

        $found = [];
        foreach ($signals as $name => $needles) {
            $found[$name] = false;
            foreach ($needles as $needle) {
                if ($needle !== '' && gov_edxdiag_strpos($lower, gov_edxdiag_lower($needle)) !== false) {
                    $found[$name] = true;
                    break;
                }
            }
        }

        return [
            'bytes' => strlen($body),
            'sha256_16' => substr(hash('sha256', $body), 0, 16),
            'title' => $title,
            'excerpt' => $excerpt,
            'signals' => $found,
        ];
    }
}

if (!function_exists('gov_edxdiag_load_session_raw')) {
    function gov_edxdiag_load_session_raw(array $liveConfig): array
    {
        $file = trim((string)($liveConfig['edxeix_session_file'] ?? ''));
        if ($file === '' || !is_file($file) || !is_readable($file)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('gov_edxdiag_safe_session_summary')) {
    function gov_edxdiag_safe_session_summary(array $liveConfig): array
    {
        $state = gov_live_session_state($liveConfig);
        return [
            'session_file_configured' => !empty($state['session_file_configured']),
            'session_file_exists' => !empty($state['session_file_exists']),
            'cookie_present' => !empty($state['cookie_present']),
            'csrf_present' => !empty($state['csrf_present']),
            'placeholder_detected' => !empty($state['placeholder_detected']),
            'updated_at' => (string)($state['updated_at'] ?? ''),
            'ready' => !empty($state['ready']),
        ];
    }
}

if (!function_exists('gov_edxdiag_transport_payload_summary')) {
    function gov_edxdiag_transport_payload_summary(array $payload): array
    {
        $clean = $payload;
        unset($clean['_mapping_status']);
        $keys = array_keys($clean);
        sort($keys);
        return [
            'field_count' => count($clean),
            'fields' => $keys,
            'payload_hash' => hash('sha256', json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }
}

if (!function_exists('gov_edxdiag_curl_step')) {
    function gov_edxdiag_curl_step(string $method, string $url, array $payload, array $session, int $timeout): array
    {
        $cookie = trim((string)($session['cookie_header'] ?? ''));
        if ($cookie === '') {
            throw new RuntimeException('EDXEIX session cookie header is missing.');
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'User-Agent: gov.cabnet.app EDXEIX submit diagnostic v3.2.21',
            'Cookie: ' . $cookie,
        ];

        $ch = curl_init();
        if (!$ch) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $method = strtoupper($method);
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($payload);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        } else {
            $opts[CURLOPT_CUSTOMREQUEST] = 'GET';
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException('EDXEIX diagnostic cURL error: ' . $error . ' (' . $errno . ')');
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $headersRaw = substr((string)$raw, 0, $headerSize);
        $body = substr((string)$raw, $headerSize);
        $headersParsed = gov_edxdiag_parse_response_headers($headersRaw);
        $location = gov_edxdiag_header_first($headersParsed, 'location');

        return [
            'method' => $method,
            'status' => $status,
            'url' => gov_edxdiag_safe_url($effectiveUrl !== '' ? $effectiveUrl : $url),
            'location' => gov_edxdiag_safe_url($location),
            'content_type' => $contentType,
            'body_fingerprint' => gov_edxdiag_body_fingerprint($body),
            '_raw_location' => $location,
        ];
    }
}

if (!function_exists('gov_edxdiag_trace_transport')) {
    function gov_edxdiag_trace_transport(array $liveConfig, array $session, array $payload, bool $followRedirects = true, int $maxRedirects = 5): array
    {
        $url = trim((string)($liveConfig['edxeix_submit_url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('EDXEIX submit URL is missing.');
        }
        $timeout = (int)($liveConfig['curl_timeout_seconds'] ?? 45);
        $timeout = max(10, min(120, $timeout));
        $method = 'POST';
        $steps = [];
        $currentUrl = $url;
        $transportPayload = gov_live_prepare_transport_payload($payload, $session);

        for ($i = 0; $i <= $maxRedirects; $i++) {
            $step = gov_edxdiag_curl_step($method, $currentUrl, $method === 'POST' ? $transportPayload : [], $session, $timeout);
            $rawLocation = (string)($step['_raw_location'] ?? '');
            unset($step['_raw_location']);
            $steps[] = $step;

            if (!$followRedirects || $rawLocation === '' || !in_array((int)$step['status'], [301, 302, 303, 307, 308], true)) {
                break;
            }

            $currentUrl = gov_edxdiag_resolve_url($currentUrl, $rawLocation);
            if ($currentUrl === '') {
                break;
            }
            // Browser behavior after common form-submit redirects.
            if (in_array((int)$step['status'], [301, 302, 303], true)) {
                $method = 'GET';
            }
        }

        return [
            'started_at' => gov_edxdiag_now(),
            'submit_url' => gov_edxdiag_safe_url($url),
            'follow_redirects' => $followRedirects,
            'max_redirects' => $maxRedirects,
            'payload_summary' => gov_edxdiag_transport_payload_summary($payload),
            'steps' => $steps,
        ];
    }
}

if (!function_exists('gov_edxdiag_classify_trace')) {
    function gov_edxdiag_classify_trace(array $trace): array
    {
        $steps = is_array($trace['steps'] ?? null) ? $trace['steps'] : [];
        if (!$steps) {
            return ['code' => 'NO_TRANSPORT_TRACE', 'message' => 'No HTTP transport trace was performed.'];
        }

        $first = $steps[0];
        $last = $steps[count($steps) - 1];
        $firstStatus = (int)($first['status'] ?? 0);
        $lastStatus = (int)($last['status'] ?? 0);
        $signals = is_array($last['body_fingerprint']['signals'] ?? null) ? $last['body_fingerprint']['signals'] : [];
        $location = (string)($first['location'] ?? '');

        if (!empty($signals['login'])) {
            return ['code' => 'SUBMIT_REDIRECT_LOGIN_REQUIRED', 'message' => 'Final page looks like a login/session page. Session likely expired or is not accepted server-side.'];
        }
        if (!empty($signals['csrf'])) {
            return ['code' => 'SUBMIT_REDIRECT_CSRF_OR_SESSION_REJECTED', 'message' => 'Final page contains CSRF/session-expiry signals. Token/session pairing likely needs adjustment.'];
        }
        if (!empty($signals['validation_error'])) {
            return ['code' => 'SUBMIT_REDIRECT_VALIDATION_ERROR', 'message' => 'Final page contains validation/error signals. Payload field names or values need inspection.'];
        }
        if (!empty($signals['success_candidate']) || !empty($signals['contract_list'])) {
            return ['code' => 'SUBMIT_REDIRECT_SUCCESS_CANDIDATE', 'message' => 'Final page contains success/list signals. Requires verifier/search proof before treating as saved.'];
        }
        if ($firstStatus >= 300 && $firstStatus < 400 && $location !== '' && $lastStatus >= 200 && $lastStatus < 300) {
            return ['code' => 'SUBMIT_REDIRECT_UNKNOWN_FINAL_200', 'message' => 'POST redirected and final page loaded, but no reliable success/error signal was detected.'];
        }
        if ($firstStatus >= 300 && $firstStatus < 400) {
            return ['code' => 'SUBMIT_REDIRECT_UNFOLLOWED_OR_OPAQUE', 'message' => 'POST returned a redirect but final destination was not conclusively classified.'];
        }
        if ($firstStatus >= 200 && $firstStatus < 300) {
            return ['code' => 'SUBMIT_HTTP_2XX_UNCLASSIFIED', 'message' => 'POST returned 2xx but page content did not classify as success or error.'];
        }
        if ($firstStatus >= 400) {
            return ['code' => 'SUBMIT_HTTP_ERROR', 'message' => 'POST returned HTTP error status.'];
        }
        return ['code' => 'SUBMIT_TRACE_UNKNOWN', 'message' => 'Trace result could not be classified.'];
    }
}


if (!function_exists('gov_edxdiag_effective_future_guard_minutes')) {
    function gov_edxdiag_effective_future_guard_minutes(): int
    {
        $config = gov_bridge_load_config();
        $configured = (int)($config['edxeix']['future_start_guard_minutes'] ?? 30);
        return max(30, $configured);
    }
}

if (!function_exists('gov_edxdiag_configured_future_guard_minutes')) {
    function gov_edxdiag_configured_future_guard_minutes(): int
    {
        $config = gov_bridge_load_config();
        return (int)($config['edxeix']['future_start_guard_minutes'] ?? 30);
    }
}

if (!function_exists('gov_edxdiag_future_guard_passes')) {
    function gov_edxdiag_future_guard_passes(?string $startedAt, int $guardMinutes): bool
    {
        if (!$startedAt) {
            return false;
        }
        $ts = strtotime($startedAt);
        return $ts !== false && $ts >= (time() + ($guardMinutes * 60));
    }
}

if (!function_exists('gov_edxdiag_diagnostic_safety_blockers')) {
    function gov_edxdiag_diagnostic_safety_blockers(array $analysis): array
    {
        $configuredGuard = gov_edxdiag_configured_future_guard_minutes();
        $effectiveGuard = gov_edxdiag_effective_future_guard_minutes();
        $startedAt = (string)($analysis['started_at'] ?? '');
        $blockers = [];

        if ($configuredGuard < 30) {
            $blockers[] = 'configured_future_guard_below_30_minimum';
        }
        if ($startedAt === '') {
            $blockers[] = 'diagnostic_missing_started_at';
        } elseif (!gov_edxdiag_future_guard_passes($startedAt, $effectiveGuard)) {
            $blockers[] = 'diagnostic_started_at_not_' . $effectiveGuard . '_min_future';
        }
        if (empty($analysis['is_real_bolt'])) {
            $blockers[] = 'diagnostic_not_real_bolt_source';
        }
        if (!empty($analysis['is_receipt_only_booking'])) {
            $blockers[] = 'diagnostic_receipt_only_booking';
        }
        if (!empty($analysis['is_lab_or_test'])) {
            $blockers[] = 'diagnostic_lab_or_test_booking';
        }
        if (!empty($analysis['terminal_status'])) {
            $blockers[] = 'diagnostic_terminal_status';
        }
        if (empty($analysis['mapping_ready'])) {
            $blockers[] = 'diagnostic_mapping_not_ready';
        }
        if (empty($analysis['technical_payload_valid']) && empty($analysis['edxeix_payload_preview'])) {
            $blockers[] = 'diagnostic_payload_unavailable';
        }

        return array_values(array_unique($blockers));
    }
}

if (!function_exists('gov_edxdiag_candidate_ready')) {
    function gov_edxdiag_candidate_ready(array $analysis): bool
    {
        $blockers = gov_edxdiag_diagnostic_safety_blockers($analysis);
        $softConfigBlockers = ['configured_future_guard_below_30_minimum'];
        $hardBlockers = array_values(array_diff($blockers, $softConfigBlockers));
        return empty($hardBlockers);
    }
}

if (!function_exists('gov_edxdiag_candidate_summary')) {
    function gov_edxdiag_candidate_summary(array $analysis): array
    {
        $effectiveGuard = gov_edxdiag_effective_future_guard_minutes();
        return [
            'booking_id' => (string)($analysis['booking_id'] ?? ''),
            'order_reference' => (string)($analysis['order_reference'] ?? ''),
            'source_system' => (string)($analysis['source_system'] ?? ''),
            'status' => (string)($analysis['status'] ?? ''),
            'started_at' => (string)($analysis['started_at'] ?? ''),
            'driver_name' => (string)($analysis['driver_name'] ?? ''),
            'plate' => (string)($analysis['plate'] ?? ''),
            'is_real_bolt' => !empty($analysis['is_real_bolt']),
            'mapping_ready' => !empty($analysis['mapping_ready']),
            'terminal_status' => !empty($analysis['terminal_status']),
            'diagnostic_future_guard_minutes' => $effectiveGuard,
            'diagnostic_future_guard_passed' => gov_edxdiag_future_guard_passes((string)($analysis['started_at'] ?? ''), $effectiveGuard),
            'diagnostic_ready_candidate' => gov_edxdiag_candidate_ready($analysis),
            'diagnostic_safety_blockers' => gov_edxdiag_diagnostic_safety_blockers($analysis),
        ];
    }
}

if (!function_exists('gov_edxdiag_candidate_report')) {
    function gov_edxdiag_candidate_report(mysqli $db, array $liveConfig, int $limit = 75): array
    {
        $limit = max(1, min(250, $limit));
        $rows = gov_live_recent_bookings($db, $limit);
        $summaries = [];
        $selectedRow = null;
        $selectedAnalysis = null;
        $readyCount = 0;

        foreach ($rows as $row) {
            $analysis = gov_live_analyze_booking($db, $row, $liveConfig);
            $summary = gov_edxdiag_candidate_summary($analysis);
            if (!empty($summary['diagnostic_ready_candidate'])) {
                $readyCount++;
                if ($selectedRow === null) {
                    $selectedRow = $row;
                    $selectedAnalysis = $analysis;
                }
            }
            if (count($summaries) < 15) {
                $summaries[] = $summary;
            }
        }

        $configuredGuard = gov_edxdiag_configured_future_guard_minutes();
        $effectiveGuard = gov_edxdiag_effective_future_guard_minutes();

        return [
            'checked_count' => count($rows),
            'ready_candidate_count' => $readyCount,
            'configured_future_guard_minutes' => $configuredGuard,
            'effective_future_guard_minutes' => $effectiveGuard,
            'future_guard_floor_applied' => $effectiveGuard > $configuredGuard,
            'selected_booking_id' => is_array($selectedAnalysis) ? (string)($selectedAnalysis['booking_id'] ?? '') : '',
            'selected_order_reference' => is_array($selectedAnalysis) ? (string)($selectedAnalysis['order_reference'] ?? '') : '',
            'rows' => $summaries,
            '_selected_row' => $selectedRow,
        ];
    }
}

if (!function_exists('gov_edxdiag_select_booking')) {
    function gov_edxdiag_select_booking(mysqli $db, array $options, array $liveConfig, ?array $candidateReport = null): ?array
    {
        $bookingId = trim((string)($options['booking_id'] ?? ''));
        $orderRef = trim((string)($options['order_reference'] ?? ''));
        $allowedBookingId = trim((string)($liveConfig['allowed_booking_id'] ?? ''));
        $allowedOrderRef = trim((string)($liveConfig['allowed_order_reference'] ?? ''));

        if ($bookingId !== '') {
            return gov_live_booking_by_id($db, $bookingId);
        }
        if ($allowedBookingId !== '') {
            return gov_live_booking_by_id($db, $allowedBookingId);
        }

        if (($orderRef !== '' || $allowedOrderRef !== '') && gov_bridge_table_exists($db, 'normalized_bookings')) {
            $target = $orderRef !== '' ? $orderRef : $allowedOrderRef;
            $columns = gov_bridge_table_columns($db, 'normalized_bookings');
            $candidateCols = ['order_reference', 'external_order_id', 'external_reference', 'source_trip_reference', 'source_trip_id'];
            $where = [];
            $params = [];
            foreach ($candidateCols as $col) {
                if (isset($columns[$col])) {
                    $where[] = gov_bridge_quote_identifier($col) . ' = ?';
                    $params[] = $target;
                }
            }
            if ($where) {
                return gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE ' . implode(' OR ', $where) . ' LIMIT 1', $params);
            }
        }

        if (is_array($candidateReport) && is_array($candidateReport['_selected_row'] ?? null)) {
            return $candidateReport['_selected_row'];
        }

        return null;
    }
}

if (!function_exists('gov_edxdiag_summarize_analysis')) {
    function gov_edxdiag_summarize_analysis(array $analysis): array
    {
        return [
            'booking_id' => (string)($analysis['booking_id'] ?? ''),
            'order_reference' => (string)($analysis['order_reference'] ?? ''),
            'source_system' => (string)($analysis['source_system'] ?? ''),
            'status' => (string)($analysis['status'] ?? ''),
            'started_at' => (string)($analysis['started_at'] ?? ''),
            'driver_name' => (string)($analysis['driver_name'] ?? ''),
            'plate' => (string)($analysis['plate'] ?? ''),
            'is_real_bolt' => !empty($analysis['is_real_bolt']),
            'is_receipt_only_booking' => !empty($analysis['is_receipt_only_booking']),
            'is_lab_or_test' => !empty($analysis['is_lab_or_test']),
            'mapping_ready' => !empty($analysis['mapping_ready']),
            'future_guard_passed' => !empty($analysis['future_guard_passed']),
            'diagnostic_future_guard_minutes' => gov_edxdiag_effective_future_guard_minutes(),
            'diagnostic_future_guard_passed' => gov_edxdiag_future_guard_passes((string)($analysis['started_at'] ?? ''), gov_edxdiag_effective_future_guard_minutes()),
            'diagnostic_safety_blockers' => gov_edxdiag_diagnostic_safety_blockers($analysis),
            'terminal_status' => !empty($analysis['terminal_status']),
            'technical_payload_valid' => !empty($analysis['technical_payload_valid']),
            'technical_blockers' => $analysis['technical_blockers'] ?? [],
            'live_submission_allowed' => !empty($analysis['live_submission_allowed']),
            'live_blockers' => $analysis['live_blockers'] ?? [],
            'payload_hash' => (string)($analysis['payload_hash'] ?? ''),
            'payload_summary' => gov_edxdiag_transport_payload_summary(is_array($analysis['edxeix_payload_preview'] ?? null) ? $analysis['edxeix_payload_preview'] : []),
        ];
    }
}

if (!function_exists('gov_edxdiag_run')) {
    function gov_edxdiag_run(array $options = []): array
    {
        $db = gov_bridge_db();
        $liveConfig = gov_live_load_config();
        $candidateLimit = (int)($options['candidate_limit'] ?? 75);
        $candidateReport = gov_edxdiag_candidate_report($db, $liveConfig, $candidateLimit);
        $booking = gov_edxdiag_select_booking($db, $options, $liveConfig, $candidateReport);
        $transportRequested = !empty($options['transport']);
        $followRedirects = array_key_exists('follow_redirects', $options) ? (bool)$options['follow_redirects'] : true;
        $confirmation = (string)($options['confirmation_phrase'] ?? '');
        $expectedConfirmation = (string)($liveConfig['confirmation_phrase'] ?? 'I UNDERSTAND SUBMIT LIVE TO EDXEIX');
        $explicitSelection = trim((string)($options['booking_id'] ?? '')) !== ''
            || trim((string)($options['order_reference'] ?? '')) !== ''
            || trim((string)($liveConfig['allowed_booking_id'] ?? '')) !== ''
            || trim((string)($liveConfig['allowed_order_reference'] ?? '')) !== '';
        $safeCandidateReport = $candidateReport;
        unset($safeCandidateReport['_selected_row']);

        if (!$booking) {
            return [
                'ok' => true,
                'started_at' => gov_edxdiag_now(),
                'transport_requested' => $transportRequested,
                'transport_performed' => false,
                'classification' => [
                    'code' => 'NO_SAFE_CANDIDATE_AVAILABLE',
                    'message' => 'No explicit booking was selected and no real future Bolt candidate passed the diagnostic readiness filter.',
                ],
                'session_summary' => gov_edxdiag_safe_session_summary($liveConfig),
                'candidate_report' => $safeCandidateReport,
                'next_action' => 'Wait for or create a real future Bolt trip, then rerun dry-run diagnostics before any one-shot transport.',
            ];
        }

        $analysis = gov_live_analyze_booking($db, $booking, $liveConfig);
        $safeAnalysis = gov_edxdiag_summarize_analysis($analysis);
        $transportBlockers = [];
        $trace = null;
        $classification = [
            'code' => 'DRY_RUN_DIAGNOSTIC_ONLY',
            'message' => 'Dry-run diagnostic completed. No EDXEIX HTTP transport was performed.',
        ];

        if ($transportRequested) {
            if (!$explicitSelection) {
                $transportBlockers[] = 'explicit_booking_or_order_required_for_transport';
            }
            if ($confirmation !== $expectedConfirmation) {
                $transportBlockers[] = 'confirmation_phrase_mismatch';
            }
            foreach (gov_edxdiag_diagnostic_safety_blockers($analysis) as $diagnosticBlocker) {
                $transportBlockers[] = $diagnosticBlocker;
            }
            if (empty($analysis['live_submission_allowed'])) {
                $transportBlockers[] = 'live_gate_not_allowed';
            }
            if (empty($liveConfig['live_submit_enabled'])) {
                $transportBlockers[] = 'live_submit_config_disabled';
            }
            if (empty($liveConfig['http_submit_enabled'])) {
                $transportBlockers[] = 'http_submit_config_disabled';
            }
            if (empty($liveConfig['edxeix_session_connected'])) {
                $transportBlockers[] = 'edxeix_session_not_connected';
            }
            if (trim((string)($liveConfig['edxeix_submit_url'] ?? '')) === '') {
                $transportBlockers[] = 'edxeix_submit_url_missing';
            }
            $transportBlockers = array_values(array_unique(array_merge($transportBlockers, $analysis['live_blockers'] ?? [])));

            if ($transportBlockers) {
                $classification = [
                    'code' => 'TRANSPORT_BLOCKED_BY_SAFETY_GATE',
                    'message' => 'Diagnostic HTTP transport was requested but blocked by live-submit safety gates.',
                ];
            } else {
                $sessionRaw = gov_edxdiag_load_session_raw($liveConfig);
                $payload = is_array($analysis['edxeix_payload_preview'] ?? null) ? $analysis['edxeix_payload_preview'] : [];
                try {
                    $trace = gov_edxdiag_trace_transport($liveConfig, $sessionRaw, $payload, $followRedirects, 6);
                    $classification = gov_edxdiag_classify_trace($trace);
                } catch (Throwable $e) {
                    $classification = [
                        'code' => 'TRANSPORT_EXCEPTION',
                        'message' => $e->getMessage(),
                    ];
                    $transportBlockers[] = 'transport_exception';
                }
            }
        }

        return [
            'ok' => $transportRequested ? ($trace !== null && ($classification['code'] ?? '') !== 'TRANSPORT_EXCEPTION') : true,
            'started_at' => gov_edxdiag_now(),
            'transport_requested' => $transportRequested,
            'transport_performed' => $trace !== null,
            'follow_redirects' => $followRedirects,
            'classification' => $classification,
            'transport_blockers' => $transportBlockers,
            'session_summary' => gov_edxdiag_safe_session_summary($liveConfig),
            'candidate_report' => $safeCandidateReport,
            'analysis' => $safeAnalysis,
            'trace' => $trace,
            'next_action' => gov_edxdiag_next_action((string)($classification['code'] ?? '')),
        ];
    }
}

if (!function_exists('gov_edxdiag_next_action')) {
    function gov_edxdiag_next_action(string $classificationCode): string
    {
        return match ($classificationCode) {
            'SUBMIT_REDIRECT_LOGIN_REQUIRED' => 'Refresh/capture a valid browser EDXEIX session, then rerun dry-run readiness before any new one-shot diagnostic.',
            'SUBMIT_REDIRECT_CSRF_OR_SESSION_REJECTED' => 'Compare captured CSRF/session fields with the rendered EDXEIX form. Token pairing likely needs browser-assisted capture.',
            'SUBMIT_REDIRECT_VALIDATION_ERROR' => 'Inspect payload field names/required fields against the live EDXEIX form contract before another attempt.',
            'SUBMIT_REDIRECT_SUCCESS_CANDIDATE' => 'Run the read-only verifier/search proof. Treat as unconfirmed until a remote reference/list match is captured.',
            'SUBMIT_REDIRECT_UNKNOWN_FINAL_200', 'SUBMIT_REDIRECT_UNFOLLOWED_OR_OPAQUE', 'SUBMIT_HTTP_2XX_UNCLASSIFIED' => 'Capture final page signals with browser-assisted proof and improve classifier before automation.',
            'TRANSPORT_BLOCKED_BY_SAFETY_GATE' => 'Keep blocked. Enable only a supervised one-shot candidate in server-only config when a real future trip is available.',
            default => 'Continue dry-run/preflight analysis. Do not enable unattended submit worker yet.',
        };
    }
}
