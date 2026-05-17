<?php
/**
 * gov.cabnet.app — Supervised pre-ride one-shot EDXEIX transport trace.
 * v3.2.31
 *
 * Purpose:
 * - Perform exactly one explicitly approved HTTP POST trace for one captured,
 *   future-safe pre-ride candidate.
 * - Keep the action separate from unattended workers and from normalized Bolt rows.
 * - Capture redirect-chain diagnostics without printing secrets, cookies, CSRF tokens,
 *   or raw HTML bodies.
 *
 * Safety contract:
 * - Default mode is dry-run. No transport without transport=1.
 * - candidate_id is required for transport; latest-ready is not enough.
 * - The exact confirmation phrase is required.
 * - The current payload hash must match the operator-approved payload hash.
 * - The rehearsal/readiness packet must still pass at runtime.
 * - The ride must still be at least the configured guard minutes in the future.
 * - Existing live-submit config gates must remain disabled for this isolated trace.
 * - No AADE/myDATA call, no queue job, no normalized_bookings write, no config write.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php';
require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php';
require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php';

if (!function_exists('gov_prtx_bool')) {
    function gov_prtx_bool($value): bool
    {
        if (is_bool($value)) { return $value; }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gov_prtx_confirmation_phrase')) {
    function gov_prtx_confirmation_phrase(): string
    {
        return 'I UNDERSTAND POST THIS ONE PRE-RIDE CANDIDATE TO EDXEIX';
    }
}

if (!function_exists('gov_prtx_now')) {
    function gov_prtx_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('gov_prtx_safe_value')) {
    function gov_prtx_safe_value($value): string
    {
        return trim((string)$value);
    }
}

if (!function_exists('gov_prtx_attempt_table_sql_note')) {
    function gov_prtx_attempt_table_sql_note(): string
    {
        return '/home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_transport_attempts.sql';
    }
}

if (!function_exists('gov_prtx_first_final_status')) {
    /** @return array{first_status:int,final_status:int,step_count:int} */
    function gov_prtx_first_final_status(?array $trace): array
    {
        $steps = is_array($trace['steps'] ?? null) ? $trace['steps'] : [];
        $first = $steps ? (int)($steps[0]['status'] ?? 0) : 0;
        $last = $steps ? (int)($steps[count($steps) - 1]['status'] ?? 0) : 0;
        return [
            'first_status' => $first,
            'final_status' => $last,
            'step_count' => count($steps),
        ];
    }
}


if (!function_exists('gov_prtx_previous_attempt_state')) {
    /** @return array<string,mixed> */
    function gov_prtx_previous_attempt_state(string $candidateId, string $payloadHash): array
    {
        $out = [
            'table_exists' => false,
            'performed_count' => 0,
            'last_attempt_id' => '',
            'last_classification_code' => '',
            'last_first_http_status' => 0,
            'last_final_http_status' => 0,
            'retry_blocked' => false,
            'warnings' => [],
        ];
        try {
            $db = gov_bridge_db();
            if (!function_exists('gov_bridge_table_exists') || !gov_bridge_table_exists($db, 'edxeix_pre_ride_transport_attempts')) {
                return $out;
            }
            $out['table_exists'] = true;
            $conditions = [];
            $params = [];
            if ($candidateId !== '') { $conditions[] = 'candidate_id = ?'; $params[] = $candidateId; }
            if ($payloadHash !== '') { $conditions[] = 'payload_hash = ?'; $params[] = $payloadHash; }
            if (!$conditions) { return $out; }
            $where = '(' . implode(' OR ', $conditions) . ') AND transport_performed = 1';
            $count = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM edxeix_pre_ride_transport_attempts WHERE ' . $where, $params);
            $out['performed_count'] = (int)($count['c'] ?? 0);
            $last = gov_bridge_fetch_one($db, 'SELECT * FROM edxeix_pre_ride_transport_attempts WHERE ' . $where . ' ORDER BY id DESC LIMIT 1', $params);
            if (is_array($last)) {
                $out['last_attempt_id'] = (string)($last['id'] ?? '');
                $out['last_classification_code'] = (string)($last['classification_code'] ?? '');
                $out['last_first_http_status'] = (int)($last['first_http_status'] ?? 0);
                $out['last_final_http_status'] = (int)($last['final_http_status'] ?? 0);
            }
            // v3.2.31: after any performed server POST trace, require manual review / closure before another POST.
            $out['retry_blocked'] = $out['performed_count'] > 0;
            return $out;
        } catch (Throwable $e) {
            $out['warnings'][] = 'previous_attempt_state_warning: ' . $e->getMessage();
            return $out;
        }
    }
}

if (!function_exists('gov_prtx_extract_form_token')) {
    function gov_prtx_extract_form_token(string $body): string
    {
        if (preg_match('/<input\b(?=[^>]*\bname=["\']_token["\'])(?=[^>]*\bvalue=["\']([^"\']*)["\'])[^>]*>/i', $body, $m)) {
            return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('/name=["\']_token["\'][^>]*value=["\']([^"\']*)["\']/i', $body, $m)) {
            return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('/value=["\']([^"\']*)["\'][^>]*name=["\']_token["\']/i', $body, $m)) {
            return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }
}

if (!function_exists('gov_prtx_form_token_diagnostic')) {
    /** @return array<string,mixed> */
    function gov_prtx_form_token_diagnostic(): array
    {
        $out = [
            'ok' => false,
            'performed' => false,
            'method' => 'GET',
            'url' => '',
            'status' => 0,
            'token_present' => false,
            'token_hash_16' => '',
            'session_csrf_hash_16' => '',
            'token_matches_session_csrf' => false,
            'body_fingerprint' => null,
            'raw_token_printed' => false,
            'raw_cookie_printed' => false,
            'raw_body_printed' => false,
            'warnings' => [],
        ];
        try {
            $liveConfig = gov_live_load_config();
            $url = trim((string)($liveConfig['edxeix_submit_url'] ?? ''));
            $out['url'] = function_exists('gov_edxdiag_safe_url') ? gov_edxdiag_safe_url($url) : $url;
            if ($url === '') { $out['warnings'][] = 'edxeix_submit_url_missing'; return $out; }
            $session = gov_edxdiag_load_session_raw($liveConfig);
            $cookie = trim((string)($session['cookie_header'] ?? ''));
            $sessionCsrf = trim((string)($session['csrf_token'] ?? ''));
            if ($sessionCsrf !== '') { $out['session_csrf_hash_16'] = substr(hash('sha256', $sessionCsrf), 0, 16); }
            if ($cookie === '') { $out['warnings'][] = 'session_cookie_missing'; return $out; }
            $timeout = (int)($liveConfig['curl_timeout_seconds'] ?? 45);
            $timeout = max(10, min(120, $timeout));
            $ch = curl_init();
            if (!$ch) { $out['warnings'][] = 'curl_init_failed'; return $out; }
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Cookie: ' . $cookie,
                    'User-Agent: gov.cabnet.app EDXEIX form token diagnostic v3.2.31',
                ],
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $out['warnings'][] = 'curl_error: ' . curl_error($ch) . ' (' . curl_errno($ch) . ')';
                curl_close($ch);
                return $out;
            }
            $out['performed'] = true;
            $out['status'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr((string)$raw, $headerSize);
            curl_close($ch);
            $token = gov_prtx_extract_form_token($body);
            $out['token_present'] = $token !== '';
            if ($token !== '') { $out['token_hash_16'] = substr(hash('sha256', $token), 0, 16); }
            $out['token_matches_session_csrf'] = $token !== '' && $sessionCsrf !== '' && hash_equals($token, $sessionCsrf);
            $out['body_fingerprint'] = function_exists('gov_edxdiag_body_fingerprint') ? gov_edxdiag_body_fingerprint($body) : [
                'bytes' => strlen($body),
                'sha256_16' => substr(hash('sha256', $body), 0, 16),
            ];
            $signals = is_array($out['body_fingerprint']['signals'] ?? null) ? $out['body_fingerprint']['signals'] : [];
            $out['ok'] = $out['performed'] && $out['status'] >= 200 && $out['status'] < 300 && $out['token_present'] && empty($signals['login']) && empty($signals['csrf']);
            if (!$out['token_present']) { $out['warnings'][] = 'form_token_not_found'; }
            if (!empty($signals['login'])) { $out['warnings'][] = 'form_get_looks_like_login_or_session_page'; }
            if (!empty($signals['csrf'])) { $out['warnings'][] = 'form_get_contains_csrf_or_session_signal'; }
            if ($out['token_present'] && !$out['token_matches_session_csrf']) { $out['warnings'][] = 'form_token_differs_from_saved_session_csrf'; }
            return $out;
        } catch (Throwable $e) {
            $out['warnings'][] = 'form_token_diagnostic_exception: ' . $e->getMessage();
            return $out;
        }
    }
}

if (!function_exists('gov_prtx_pre_transport_hold_blockers')) {
    /** @param array<string,mixed> $rehearsal @param array<string,mixed> $formDiag @return array<int,string> */
    function gov_prtx_pre_transport_hold_blockers(array $rehearsal, array $formDiag): array
    {
        $blockers = [];
        $packet = is_array($rehearsal['operator_rehearsal_packet'] ?? null) ? $rehearsal['operator_rehearsal_packet'] : [];
        $candidateId = (string)($packet['candidate_id'] ?? '');
        $payloadHash = (string)($packet['payload_hash'] ?? '');
        $closure = is_array($rehearsal['readiness_packet']['closure_state'] ?? null) ? $rehearsal['readiness_packet']['closure_state'] : [];
        if (!empty($closure['closed']) || !empty($closure['manual_submitted_v0'])) {
            $blockers[] = 'candidate_closed_or_manually_submitted_via_v0';
        }
        $previous = gov_prtx_previous_attempt_state($candidateId, $payloadHash);
        if (!empty($previous['retry_blocked'])) {
            $blockers[] = 'previous_server_transport_attempt_requires_manual_review_or_new_candidate';
        }
        if (empty($formDiag['ok'])) {
            $blockers[] = 'edxeix_form_token_diagnostic_not_ready';
        }
        // v3.2.31 is intentionally a safe hold/retry-prevention patch. A later patch may integrate a freshly fetched form token.
        $blockers[] = 'transport_disabled_pending_fresh_form_token_integration_patch';
        return array_values(array_unique($blockers));
    }
}

if (!function_exists('gov_prtx_transport_blockers')) {
    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $rehearsal
     * @return array<int,string>
     */
    function gov_prtx_transport_blockers(array $options, array $rehearsal): array
    {
        $blockers = [];
        $candidateId = (int)($options['candidate_id'] ?? 0);
        $transportRequested = gov_prtx_bool($options['transport'] ?? false);
        $confirmation = gov_prtx_safe_value($options['confirmation_phrase'] ?? '');
        $expectedPayloadHash = gov_prtx_safe_value($options['expected_payload_hash'] ?? '');
        $packet = is_array($rehearsal['operator_rehearsal_packet'] ?? null) ? $rehearsal['operator_rehearsal_packet'] : [];
        $currentPayloadHash = gov_prtx_safe_value($packet['payload_hash'] ?? '');
        $live = is_array($rehearsal['live_gate_summary'] ?? null) ? $rehearsal['live_gate_summary'] : [];

        if (!$transportRequested) {
            return [];
        }
        if ($candidateId <= 0) {
            $blockers[] = 'candidate_id_required_for_transport';
        }
        if (empty($rehearsal['ready_for_later_supervised_transport_patch'])) {
            $blockers[] = 'transport_rehearsal_not_ready';
        }
        foreach (($rehearsal['rehearsal_blockers'] ?? []) as $blocker) {
            $blockers[] = 'rehearsal_' . (string)$blocker;
        }
        if ($confirmation !== gov_prtx_confirmation_phrase()) {
            $blockers[] = 'confirmation_phrase_mismatch';
        }
        if ($expectedPayloadHash === '') {
            $blockers[] = 'expected_payload_hash_required';
        } elseif ($currentPayloadHash === '' || !hash_equals($currentPayloadHash, $expectedPayloadHash)) {
            $blockers[] = 'expected_payload_hash_mismatch';
        }
        if (!empty($live['live_submit_enabled'])) {
            $blockers[] = 'live_submit_config_enabled_disable_for_isolated_trace';
        }
        if (!empty($live['http_submit_enabled'])) {
            $blockers[] = 'http_submit_config_enabled_disable_for_isolated_trace';
        }
        if (empty($live['session_ready'])) {
            $blockers[] = 'edxeix_session_not_ready';
        }
        if (empty($live['submit_url_configured'])) {
            $blockers[] = 'edxeix_submit_url_missing';
        }
        if (!empty($rehearsal['readiness_packet']['duplicate_check']['duplicate_success_detected'])) {
            $blockers[] = 'duplicate_success_detected';
        }
        return array_values(array_unique($blockers));
    }
}

if (!function_exists('gov_prtx_attempt_insert')) {
    /**
     * Writes sanitized attempt metadata only when the optional table exists.
     * No raw cookie, CSRF, or response body is stored here.
     *
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    function gov_prtx_attempt_insert(array $result): array
    {
        $out = [
            'table_exists' => false,
            'attempt_id' => 0,
            'written' => false,
            'warning' => '',
        ];
        try {
            $db = gov_bridge_db();
            if (!gov_bridge_table_exists($db, 'edxeix_pre_ride_transport_attempts')) {
                return $out;
            }
            $out['table_exists'] = true;
            $packet = is_array($result['operator_transport_packet'] ?? null) ? $result['operator_transport_packet'] : [];
            $status = gov_prtx_first_final_status(is_array($result['trace'] ?? null) ? $result['trace'] : null);
            $row = [
                'candidate_id' => (string)($packet['candidate_id'] ?? ''),
                'transport_id' => (string)($packet['transport_id'] ?? ''),
                'rehearsal_id' => (string)($packet['rehearsal_id'] ?? ''),
                'source_hash_16' => (string)($packet['source_hash_16'] ?? ''),
                'payload_hash' => (string)($packet['payload_hash'] ?? ''),
                'classification_code' => (string)($result['classification']['code'] ?? ''),
                'classification_message' => (string)($result['classification']['message'] ?? ''),
                'transport_requested' => !empty($result['transport_requested']) ? '1' : '0',
                'transport_performed' => !empty($result['transport_performed']) ? '1' : '0',
                'first_http_status' => (string)$status['first_status'],
                'final_http_status' => (string)$status['final_status'],
                'step_count' => (string)$status['step_count'],
                'trace_json' => json_encode($result['trace'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'blockers_json' => json_encode($result['transport_blockers'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => gov_prtx_now(),
            ];
            $out['attempt_id'] = gov_bridge_insert_row($db, 'edxeix_pre_ride_transport_attempts', $row);
            $out['written'] = $out['attempt_id'] > 0;
            return $out;
        } catch (Throwable $e) {
            $out['warning'] = 'Attempt metadata insert skipped/failed: ' . $e->getMessage();
            return $out;
        }
    }
}

if (!function_exists('gov_prtx_run')) {
    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    function gov_prtx_run(array $options = []): array
    {
        $candidateId = (int)($options['candidate_id'] ?? 0);
        $transportRequested = gov_prtx_bool($options['transport'] ?? false);
        $followRedirects = array_key_exists('follow_redirects', $options) ? gov_prtx_bool($options['follow_redirects']) : true;
        $rehearsal = gov_prt_run($candidateId > 0 ? ['candidate_id' => $candidateId] : ['latest_ready' => true]);
        $formTokenDiagnostic = gov_prtx_form_token_diagnostic();
        $preTransportHoldBlockers = gov_prtx_pre_transport_hold_blockers($rehearsal, $formTokenDiagnostic);
        $blockers = gov_prtx_transport_blockers($options, $rehearsal);
        if ($transportRequested) { $blockers = array_values(array_unique(array_merge($blockers, $preTransportHoldBlockers))); }
        $packet = is_array($rehearsal['operator_rehearsal_packet'] ?? null) ? $rehearsal['operator_rehearsal_packet'] : [];
        $payload = is_array($packet['payload_preview'] ?? null) ? $packet['payload_preview'] : [];
        $trace = null;
        $classification = [
            'code' => !empty($rehearsal['ready_for_later_supervised_transport_patch']) ? 'PRE_RIDE_TRANSPORT_TRACE_HELD_FOR_SESSION_REFRESH' : 'PRE_RIDE_TRANSPORT_TRACE_BLOCKED',
            'message' => !empty($rehearsal['ready_for_later_supervised_transport_patch'])
                ? 'Candidate is structurally ready, but v3.2.31 is holding server-side transport until manual closures/retry prevention and fresh EDXEIX form-token diagnostics are resolved. No submit was performed.'
                : 'Candidate is blocked before transport trace. No submit was performed.',
        ];
        if (!empty($rehearsal['ready_for_later_supervised_transport_patch']) && !$preTransportHoldBlockers) {
            $classification = [
                'code' => 'PRE_RIDE_TRANSPORT_TRACE_ARMABLE',
                'message' => 'Candidate is armable for a supervised one-shot EDXEIX transport trace. No submit was performed.',
            ];
        }

        if ($transportRequested) {
            if ($blockers) {
                $classification = [
                    'code' => 'PRE_RIDE_TRANSPORT_TRACE_BLOCKED',
                    'message' => 'Supervised one-shot transport was requested but blocked by runtime safety checks. No submit was performed.',
                ];
            } else {
                try {
                    $liveConfig = gov_live_load_config();
                    $sessionRaw = gov_edxdiag_load_session_raw($liveConfig);
                    $trace = gov_edxdiag_trace_transport($liveConfig, $sessionRaw, $payload, $followRedirects, 6);
                    $traceClass = gov_edxdiag_classify_trace($trace);
                    $classification = [
                        'code' => 'PRE_RIDE_TRANSPORT_TRACE_PERFORMED_' . (string)($traceClass['code'] ?? 'UNCLASSIFIED'),
                        'message' => 'One supervised pre-ride EDXEIX HTTP POST trace was performed. Treat as unconfirmed until verified in EDXEIX. ' . (string)($traceClass['message'] ?? ''),
                    ];
                } catch (Throwable $e) {
                    $classification = [
                        'code' => 'PRE_RIDE_TRANSPORT_TRACE_EXCEPTION',
                        'message' => $e->getMessage(),
                    ];
                    $blockers[] = 'transport_exception';
                }
            }
        }

        $payloadHash = (string)($packet['payload_hash'] ?? '');
        $sourceHash16 = (string)($packet['source_hash_16'] ?? '');
        $candidateIdOut = (string)($packet['candidate_id'] ?? ($candidateId > 0 ? (string)$candidateId : ''));
        $transportId = 'PRTX-' . ($candidateIdOut !== '' ? $candidateIdOut : 'latest') . '-' . $sourceHash16 . '-' . substr($payloadHash, 0, 16);

        $result = [
            'ok' => !$transportRequested || ($trace !== null && empty($blockers)),
            'version' => 'v3.2.32-closure-retry-prevention-form-token-diagnostic',
            'started_at' => gov_prtx_now(),
            'classification' => $classification,
            'transport_requested' => $transportRequested,
            'transport_performed' => $trace !== null,
            'follow_redirects' => $followRedirects,
            'config_written' => false,
            'safety' => [
                'edxeix_transport_possible_only_with_transport_flag_and_confirmation' => true,
                'edxeix_transport_performed' => $trace !== null,
                'aade_call' => false,
                'queue_job' => false,
                'normalized_booking_write' => false,
                'live_config_write' => false,
                'raw_cookie_printed' => false,
                'raw_csrf_printed' => false,
                'raw_response_body_printed' => false,
            ],
            'transport_blockers' => array_values(array_unique($blockers)),
            'pre_transport_hold_blockers' => $preTransportHoldBlockers,
            'form_token_diagnostic' => $formTokenDiagnostic,
            'previous_attempt_state' => gov_prtx_previous_attempt_state((string)($packet['candidate_id'] ?? ''), (string)($packet['payload_hash'] ?? '')),
            'required_confirmation_phrase' => gov_prtx_confirmation_phrase(),
            'expected_payload_hash_required_for_transport' => $payloadHash,
            'operator_transport_packet' => [
                'transport_id' => $transportId,
                'candidate_id' => $candidateIdOut,
                'rehearsal_id' => (string)($packet['rehearsal_id'] ?? ''),
                'readiness_packet_id' => (string)($packet['readiness_packet_id'] ?? ''),
                'source_hash_16' => $sourceHash16,
                'payload_hash' => $payloadHash,
                'payload_hash_16' => substr($payloadHash, 0, 16),
                'pickup_datetime' => (string)($packet['pickup_datetime'] ?? ''),
                'future_guard_minutes' => (int)($packet['future_guard_minutes'] ?? 30),
                'future_guard_expires_at' => (string)($packet['future_guard_expires_at'] ?? ''),
                'minutes_until_pickup' => (int)($packet['minutes_until_pickup'] ?? 0),
                'lessor_id' => (string)($packet['lessor_id'] ?? ''),
                'driver_id' => (string)($packet['driver_id'] ?? ''),
                'vehicle_id' => (string)($packet['vehicle_id'] ?? ''),
                'starting_point_id' => (string)($packet['starting_point_id'] ?? ''),
                'driver_name' => (string)($packet['driver_name'] ?? ''),
                'vehicle_plate' => (string)($packet['vehicle_plate'] ?? ''),
                'pickup_address' => (string)($packet['pickup_address'] ?? ''),
                'dropoff_address' => (string)($packet['dropoff_address'] ?? ''),
                'price_amount' => (string)($packet['price_amount'] ?? ''),
                'price_currency' => (string)($packet['price_currency'] ?? ''),
                'payload_preview' => $payload,
            ],
            'live_gate_summary' => $rehearsal['live_gate_summary'] ?? [],
            'rehearsal_packet' => $rehearsal,
            'trace' => $trace,
            'attempt_metadata' => [
                'table_sql' => gov_prtx_attempt_table_sql_note(),
                'table_optional' => true,
                'written' => false,
                'attempt_id' => 0,
            ],
            'next_action' => $trace !== null
                ? 'Immediately verify in the EDXEIX portal/list. If saved, capture proof and do not retry this candidate. If not confirmed, treat as diagnostic only.'
                : 'Review the hold blockers/form-token diagnostic. Do not POST again until a new patch integrates a fresh EDXEIX form token and the candidate is not manually closed.',
        ];

        if ($transportRequested) {
            $insert = gov_prtx_attempt_insert($result);
            $result['attempt_metadata'] = array_replace($result['attempt_metadata'], $insert);
        }

        return $result;
    }
}
