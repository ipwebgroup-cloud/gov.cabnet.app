<?php
/**
 * gov.cabnet.app — Supervised pre-ride one-shot EDXEIX transport trace.
 * v3.2.37
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


if (!function_exists('gov_prtx_canonical_text')) {
    function gov_prtx_canonical_text($value): string
    {
        $text = trim((string)$value);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($text, 'UTF-8');
        }
        return strtolower($text);
    }
}

if (!function_exists('gov_prtx_canonical_plate')) {
    function gov_prtx_canonical_plate($value): string
    {
        $text = strtoupper(trim((string)$value));
        return preg_replace('/[^A-Z0-9Α-Ω]/u', '', $text) ?? $text;
    }
}

if (!function_exists('gov_prtx_canonical_datetime')) {
    function gov_prtx_canonical_datetime($value): string
    {
        $text = trim((string)$value);
        if ($text === '') { return ''; }
        $ts = strtotime($text);
        if ($ts === false) { return $text; }
        return date('Y-m-d H:i:s', $ts);
    }
}

if (!function_exists('gov_prtx_expectation_value')) {
    function gov_prtx_expectation_value(array $options, string $key): string
    {
        return trim((string)($options[$key] ?? ''));
    }
}

if (!function_exists('gov_prtx_candidate_identity_from_rehearsal')) {
    /** @param array<string,mixed> $rehearsal @return array<string,string> */
    function gov_prtx_candidate_identity_from_rehearsal(array $rehearsal): array
    {
        $packet = is_array($rehearsal['operator_rehearsal_packet'] ?? null) ? $rehearsal['operator_rehearsal_packet'] : [];
        $rp = is_array($rehearsal['readiness_packet'] ?? null) ? $rehearsal['readiness_packet'] : [];
        $candidate = is_array($rp['candidate'] ?? null) ? $rp['candidate'] : [];
        return [
            'candidate_id' => (string)($packet['candidate_id'] ?? $candidate['candidate_id'] ?? ''),
            'customer_name' => (string)($candidate['customer_name'] ?? ''),
            'driver_name' => (string)($packet['driver_name'] ?? $candidate['driver_name'] ?? ''),
            'vehicle_plate' => (string)($packet['vehicle_plate'] ?? $candidate['vehicle_plate'] ?? ''),
            'pickup_datetime' => (string)($packet['pickup_datetime'] ?? $candidate['pickup_datetime'] ?? ''),
        ];
    }
}

if (!function_exists('gov_prtx_identity_lock_report')) {
    /** @param array<string,mixed> $options @param array<string,mixed> $rehearsal @return array<string,mixed> */
    function gov_prtx_identity_lock_report(array $options, array $rehearsal): array
    {
        $actual = gov_prtx_candidate_identity_from_rehearsal($rehearsal);
        $expected = [
            'customer_name' => gov_prtx_expectation_value($options, 'expected_customer'),
            'driver_name' => gov_prtx_expectation_value($options, 'expected_driver'),
            'vehicle_plate' => gov_prtx_expectation_value($options, 'expected_vehicle'),
            'pickup_datetime' => gov_prtx_expectation_value($options, 'expected_pickup'),
        ];
        $checks = [];
        $missing = [];
        $mismatches = [];
        foreach ($expected as $field => $expectedValue) {
            $actualValue = (string)($actual[$field] ?? '');
            if ($expectedValue === '') {
                $missing[] = $field;
                $checks[] = [
                    'field' => $field,
                    'expected' => '',
                    'actual' => $actualValue,
                    'match' => false,
                    'reason' => 'expected_value_missing',
                ];
                continue;
            }
            if ($field === 'vehicle_plate') {
                $match = gov_prtx_canonical_plate($expectedValue) === gov_prtx_canonical_plate($actualValue);
            } elseif ($field === 'pickup_datetime') {
                $match = gov_prtx_canonical_datetime($expectedValue) === gov_prtx_canonical_datetime($actualValue);
            } else {
                $match = gov_prtx_canonical_text($expectedValue) === gov_prtx_canonical_text($actualValue);
            }
            if (!$match) { $mismatches[] = $field; }
            $checks[] = [
                'field' => $field,
                'expected' => $expectedValue,
                'actual' => $actualValue,
                'match' => $match,
                'reason' => $match ? 'match' : 'expected_value_does_not_match_candidate',
            ];
        }
        return [
            'required_for_transport' => true,
            'candidate_id' => $actual['candidate_id'],
            'checks' => $checks,
            'missing_expectations' => $missing,
            'mismatches' => $mismatches,
            'locked' => !$missing && !$mismatches,
            'note' => 'Transport requires explicit expected customer, driver, vehicle, and pickup datetime. This prevents latest-mail confusion.',
        ];
    }
}

if (!function_exists('gov_prtx_identity_blockers')) {
    /** @param array<string,mixed> $identityLock @return array<int,string> */
    function gov_prtx_identity_blockers(array $identityLock): array
    {
        $blockers = [];
        foreach (($identityLock['missing_expectations'] ?? []) as $field) {
            $blockers[] = 'expected_' . (string)$field . '_required_for_transport';
        }
        foreach (($identityLock['mismatches'] ?? []) as $field) {
            $blockers[] = 'identity_lock_mismatch_' . (string)$field;
        }
        return $blockers;
    }
}

if (!function_exists('gov_prtx_recent_candidates')) {
    /** @return array<string,mixed> */
    function gov_prtx_recent_candidates(int $limit = 10): array
    {
        $out = ['ok' => true, 'table_exists' => false, 'limit' => $limit, 'candidates' => [], 'warnings' => []];
        try {
            $db = gov_bridge_db();
            if (!function_exists('gov_bridge_table_exists') || !gov_bridge_table_exists($db, 'edxeix_pre_ride_candidates')) {
                return $out;
            }
            $out['table_exists'] = true;
            $limit = max(1, min(50, $limit));
            $sql = 'SELECT id, status, readiness_status, ready_for_edxeix, pickup_datetime, customer_name, driver_name, vehicle_plate, pickup_address, dropoff_address, price_amount, price_currency, created_at, updated_at FROM edxeix_pre_ride_candidates ORDER BY id DESC LIMIT ' . $limit;
            $rows = gov_bridge_fetch_all($db, $sql, []);
            foreach ($rows as $row) {
                $out['candidates'][] = [
                    'candidate_id' => (string)($row['id'] ?? ''),
                    'status' => (string)($row['status'] ?? ''),
                    'readiness_status' => (string)($row['readiness_status'] ?? ''),
                    'ready_for_edxeix' => !empty($row['ready_for_edxeix']),
                    'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
                    'customer_name' => (string)($row['customer_name'] ?? ''),
                    'driver_name' => (string)($row['driver_name'] ?? ''),
                    'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
                    'pickup_address' => (string)($row['pickup_address'] ?? ''),
                    'dropoff_address' => (string)($row['dropoff_address'] ?? ''),
                    'price' => trim((string)($row['price_amount'] ?? '') . ' ' . (string)($row['price_currency'] ?? '')),
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'updated_at' => (string)($row['updated_at'] ?? ''),
                ];
            }
            return $out;
        } catch (Throwable $e) {
            $out['ok'] = false;
            $out['warnings'][] = 'recent_candidates_warning: ' . $e->getMessage();
            return $out;
        }
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
            'fresh_token_available_for_transport' => false,
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
            // v3.2.37: after any performed server POST trace, require manual review / closure before another POST.
            $out['retry_blocked'] = $out['performed_count'] > 0;
            return $out;
        } catch (Throwable $e) {
            $out['warnings'][] = 'previous_attempt_state_warning: ' . $e->getMessage();
            return $out;
        }
    }
}

if (!function_exists('gov_prtx_html_attr')) {
    function gov_prtx_html_attr(string $tag, string $attr): string
    {
        $attr = preg_quote($attr, '/');
        if (preg_match('/\b' . $attr . '\s*=\s*["\']([^"\']*)["\']/i', $tag, $m)) {
            return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('/\b' . $attr . '\s*=\s*([^\s>]+)/i', $tag, $m)) {
            return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
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

if (!function_exists('gov_prtx_create_form_url')) {
    function gov_prtx_create_form_url(array $liveConfig): string
    {
        $configured = trim((string)($liveConfig['edxeix_create_url'] ?? $liveConfig['edxeix_form_url'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }
        $submit = trim((string)($liveConfig['edxeix_submit_url'] ?? ''));
        if ($submit === '') {
            return '';
        }
        $submit = rtrim($submit, '/');
        if (preg_match('#/create$#', $submit)) {
            return $submit;
        }
        if (preg_match('#/dashboard/lease-agreement$#', $submit)) {
            return $submit . '/create';
        }
        return $submit . '/create';
    }
}

if (!function_exists('gov_prtx_form_field_names_from_fragment')) {
    /** @return array<string,mixed> */
    function gov_prtx_form_field_names_from_fragment(string $fragment): array
    {
        $out = [
            'input_names' => [],
            'hidden_names' => [],
            'select_names' => [],
            'textarea_names' => [],
            'input_count' => 0,
            'hidden_count' => 0,
            'select_count' => 0,
            'textarea_count' => 0,
        ];
        if (preg_match_all('/<input\b[^>]*>/i', $fragment, $matches)) {
            $names = [];
            $hidden = [];
            foreach ($matches[0] as $tag) {
                $name = gov_prtx_html_attr((string)$tag, 'name');
                if ($name === '') { continue; }
                $names[] = $name;
                $type = strtolower(gov_prtx_html_attr((string)$tag, 'type'));
                if ($type === 'hidden') { $hidden[] = $name; }
            }
            $out['input_names'] = array_values(array_unique($names));
            $out['hidden_names'] = array_values(array_unique($hidden));
        }
        if (preg_match_all('/<select\b[^>]*>/i', $fragment, $matches)) {
            $names = [];
            foreach ($matches[0] as $tag) {
                $name = gov_prtx_html_attr((string)$tag, 'name');
                if ($name !== '') { $names[] = $name; }
            }
            $out['select_names'] = array_values(array_unique($names));
        }
        if (preg_match_all('/<textarea\b[^>]*>/i', $fragment, $matches)) {
            $names = [];
            foreach ($matches[0] as $tag) {
                $name = gov_prtx_html_attr((string)$tag, 'name');
                if ($name !== '') { $names[] = $name; }
            }
            $out['textarea_names'] = array_values(array_unique($names));
        }
        $out['input_count'] = count($out['input_names']);
        $out['hidden_count'] = count($out['hidden_names']);
        $out['select_count'] = count($out['select_names']);
        $out['textarea_count'] = count($out['textarea_names']);
        return $out;
    }
}

if (!function_exists('gov_prtx_field_present_with_aliases')) {
    /** @param array<int,string> $names @param array<int,string> $aliases */
    function gov_prtx_field_present_with_aliases(array $names, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            if (in_array($alias, $names, true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('gov_prtx_expected_field_report')) {
    /** @param array<int,string> $names @return array{present:array<int,string>,missing:array<int,string>,aliases:array<string,array<int,string>>} */
    function gov_prtx_expected_field_report(array $names): array
    {
        $aliases = [
            'lessor' => ['lessor'],
            'driver' => ['driver'],
            'vehicle' => ['vehicle'],
            // EDXEIX create form uses starting_point_id, while older bridge payloads may also carry starting_point.
            'starting_point' => ['starting_point', 'starting_point_id'],
            'boarding_point' => ['boarding_point'],
            'disembark_point' => ['disembark_point'],
            // EDXEIX form uses lessee[name] plus lessee[type], not a flat lessee field.
            'lessee' => ['lessee', 'lessee[name]', 'lessee[type]'],
            'started_at' => ['started_at'],
            'ended_at' => ['ended_at'],
            'price' => ['price'],
        ];
        $present = [];
        $missing = [];
        foreach ($aliases as $canonical => $fieldAliases) {
            if (gov_prtx_field_present_with_aliases($names, $fieldAliases)) {
                $present[] = $canonical;
            } else {
                $missing[] = $canonical;
            }
        }
        return ['present' => $present, 'missing' => $missing, 'aliases' => $aliases];
    }
}

if (!function_exists('gov_prtx_form_candidate_score')) {
    /** @param array<int,string> $names */
    function gov_prtx_form_candidate_score(array $names, string $action): int
    {
        $report = gov_prtx_expected_field_report($names);
        $score = count($report['present']) * 10;
        foreach (['_token', 'lessor', 'driver', 'vehicle', 'starting_point_id', 'boarding_point', 'disembark_point', 'started_at', 'ended_at', 'price'] as $important) {
            if (in_array($important, $names, true)) { $score += 2; }
        }
        $lowerAction = strtolower($action);
        if (strpos($lowerAction, 'logout') !== false) { $score -= 100; }
        if (strpos($lowerAction, 'lease-agreement') !== false) { $score += 8; }
        if (strpos($lowerAction, 'create') !== false || $action === '') { $score += 2; }
        return $score;
    }
}

if (!function_exists('gov_prtx_extract_form_summary')) {
    /** @return array<string,mixed> */
    function gov_prtx_extract_form_summary(string $body, string $finalUrl): array
    {
        $summary = [
            'form_present' => false,
            'form_count' => 0,
            'selected_form_index' => -1,
            'selected_form_score' => 0,
            'selected_form_reason' => '',
            'form_method' => '',
            'form_action' => '',
            'form_action_safe' => '',
            'input_count' => 0,
            'input_names' => [],
            'hidden_count' => 0,
            'hidden_names' => [],
            'token_field_present' => false,
            'token_hash_16' => '',
            'select_count' => 0,
            'select_names' => [],
            'textarea_count' => 0,
            'textarea_names' => [],
            'required_expected_fields_present' => [],
            'required_expected_fields_missing' => [],
            'expected_field_aliases' => [],
            'candidate_forms' => [],
        ];

        $token = gov_prtx_extract_form_token($body);
        if ($token !== '') {
            $summary['token_field_present'] = true;
            $summary['token_hash_16'] = substr(hash('sha256', $token), 0, 16);
        }

        $forms = [];
        if (preg_match_all('/<form\b[^>]*>.*?<\/form>/is', $body, $matches)) {
            $forms = $matches[0];
        }
        if (!$forms && preg_match_all('/<form\b[^>]*>/i', $body, $matches)) {
            $forms = $matches[0];
        }
        $summary['form_count'] = count($forms);

        $best = null;
        foreach ($forms as $idx => $formHtml) {
            $tag = '';
            if (preg_match('/<form\b[^>]*>/i', (string)$formHtml, $m)) { $tag = (string)$m[0]; }
            $method = strtoupper(gov_prtx_html_attr($tag, 'method'));
            $method = $method !== '' ? $method : 'GET';
            $action = gov_prtx_html_attr($tag, 'action');
            if ($action !== '' && function_exists('gov_edxdiag_resolve_url')) {
                $action = gov_edxdiag_resolve_url($finalUrl, $action);
            } elseif ($action === '') {
                $action = $finalUrl;
            }
            $fields = gov_prtx_form_field_names_from_fragment((string)$formHtml);
            $allNames = array_values(array_unique(array_merge($fields['input_names'], $fields['select_names'], $fields['textarea_names'])));
            $expected = gov_prtx_expected_field_report($allNames);
            $score = gov_prtx_form_candidate_score($allNames, $action);
            $candidate = [
                'index' => $idx,
                'score' => $score,
                'method' => $method,
                'action_safe' => function_exists('gov_edxdiag_safe_url') ? gov_edxdiag_safe_url($action) : $action,
                'field_count' => count($allNames),
                'expected_present_count' => count($expected['present']),
                'expected_missing_count' => count($expected['missing']),
                'looks_like_logout' => strpos(strtolower($action), 'logout') !== false,
            ];
            $summary['candidate_forms'][] = $candidate;
            if ($best === null || $score > (int)$best['score']) {
                $best = array_merge($candidate, [
                    'tag' => $tag,
                    'method_raw' => $method,
                    'action_raw' => $action,
                    'fields' => $fields,
                    'all_names' => $allNames,
                    'expected' => $expected,
                ]);
            }
        }

        // If the page has no explicit form wrapper or parsing failed, still summarize global fields.
        if ($best === null) {
            $fields = gov_prtx_form_field_names_from_fragment($body);
            $allNames = array_values(array_unique(array_merge($fields['input_names'], $fields['select_names'], $fields['textarea_names'])));
            $expected = gov_prtx_expected_field_report($allNames);
            $best = [
                'index' => -1,
                'score' => gov_prtx_form_candidate_score($allNames, $finalUrl),
                'method_raw' => '',
                'action_raw' => '',
                'fields' => $fields,
                'all_names' => $allNames,
                'expected' => $expected,
            ];
        }

        $fields = $best['fields'];
        $summary['form_present'] = count($forms) > 0 || count($best['all_names']) > 0;
        $summary['selected_form_index'] = (int)$best['index'];
        $summary['selected_form_score'] = (int)$best['score'];
        $summary['selected_form_reason'] = $best['index'] >= 0 ? 'highest_expected_field_score' : 'global_field_fallback';
        $summary['form_method'] = (string)$best['method_raw'];
        $summary['form_action'] = (string)$best['action_raw'];
        $summary['form_action_safe'] = function_exists('gov_edxdiag_safe_url') ? gov_edxdiag_safe_url((string)$best['action_raw']) : (string)$best['action_raw'];
        $summary['input_names'] = $fields['input_names'];
        $summary['input_count'] = $fields['input_count'];
        $summary['hidden_names'] = $fields['hidden_names'];
        $summary['hidden_count'] = $fields['hidden_count'];
        $summary['select_names'] = $fields['select_names'];
        $summary['select_count'] = $fields['select_count'];
        $summary['textarea_names'] = $fields['textarea_names'];
        $summary['textarea_count'] = $fields['textarea_count'];
        $summary['required_expected_fields_present'] = $best['expected']['present'];
        $summary['required_expected_fields_missing'] = $best['expected']['missing'];
        $summary['expected_field_aliases'] = $best['expected']['aliases'];

        // Token may be outside the selected form in some Laravel layouts; still report global token hash.
        if (!$summary['token_field_present'] && in_array('_token', $summary['input_names'], true)) {
            $summary['token_field_present'] = true;
        }

        return $summary;
    }
}

if (!function_exists('gov_prtx_create_form_context_ready')) {
    /** @param array<string,mixed> $out */
    function gov_prtx_create_form_context_ready(array $out): bool
    {
        $summary = is_array($out['form_summary'] ?? null) ? $out['form_summary'] : [];
        $finalUrl = strtolower((string)($out['final_url'] ?? ''));
        $present = is_array($summary['required_expected_fields_present'] ?? null) ? $summary['required_expected_fields_present'] : [];
        $missing = is_array($summary['required_expected_fields_missing'] ?? null) ? $summary['required_expected_fields_missing'] : [];
        $critical = ['lessor', 'driver', 'vehicle', 'starting_point', 'boarding_point', 'disembark_point', 'lessee', 'started_at', 'ended_at', 'price'];
        $criticalPresent = true;
        foreach ($critical as $field) {
            if (!in_array($field, $present, true)) {
                $criticalPresent = false;
                break;
            }
        }
        return !empty($out['performed'])
            && (int)($out['status'] ?? 0) >= 200 && (int)($out['status'] ?? 0) < 300
            && !empty($out['token_present'])
            && !empty($summary['form_present'])
            && $criticalPresent
            && strpos($finalUrl, '/dashboard/lease-agreement/create') !== false;
    }
}

if (!function_exists('gov_prtx_form_get_step')) {
    /** @return array<string,mixed> */
    function gov_prtx_form_get_step(string $url, string $cookie, int $timeout, string $referer = ''): array
    {
        $ch = curl_init();
        if (!$ch) {
            throw new RuntimeException('Unable to initialize cURL for form diagnostic.');
        }
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Cookie: ' . $cookie,
            'User-Agent: gov.cabnet.app EDXEIX create-form token diagnostic v3.2.37',
        ];
        if ($referer !== '') {
            $headers[] = 'Referer: ' . $referer;
        }
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
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException('EDXEIX create-form diagnostic cURL error: ' . $error . ' (' . $errno . ')');
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headersRaw = substr((string)$raw, 0, $headerSize);
        $body = substr((string)$raw, $headerSize);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        $headersParsed = function_exists('gov_edxdiag_parse_response_headers') ? gov_edxdiag_parse_response_headers($headersRaw) : [];
        $location = function_exists('gov_edxdiag_header_first') ? gov_edxdiag_header_first($headersParsed, 'location') : '';
        return [
            'status' => $status,
            'url' => function_exists('gov_edxdiag_safe_url') ? gov_edxdiag_safe_url($url) : $url,
            'location' => $location !== '' && function_exists('gov_edxdiag_resolve_url') ? gov_edxdiag_safe_url(gov_edxdiag_resolve_url($url, $location)) : $location,
            'location_raw' => $location,
            'content_type' => $contentType,
            'body' => $body,
            'body_fingerprint' => function_exists('gov_edxdiag_body_fingerprint') ? gov_edxdiag_body_fingerprint($body) : [
                'bytes' => strlen($body),
                'sha256_16' => substr(hash('sha256', $body), 0, 16),
            ],
        ];
    }
}

if (!function_exists('gov_prtx_form_token_diagnostic')) {
    /** @return array<string,mixed> */
    function gov_prtx_form_token_diagnostic(bool $includeInternalToken = false): array
    {
        $out = [
            'ok' => false,
            'performed' => false,
            'method' => 'GET',
            'url' => '',
            'create_url' => '',
            'final_url' => '',
            'status' => 0,
            'redirect_count' => 0,
            'steps' => [],
            'token_present' => false,
            'token_hash_16' => '',
            'session_csrf_hash_16' => '',
            'token_matches_session_csrf' => false,
            'form_summary' => null,
            'body_fingerprint' => null,
            'raw_token_printed' => false,
            'raw_cookie_printed' => false,
            'raw_body_printed' => false,
            'warnings' => [],
            'fresh_token_available_for_transport' => false,
        ];
        try {
            $liveConfig = gov_live_load_config();
            $submitUrl = trim((string)($liveConfig['edxeix_submit_url'] ?? ''));
            $createUrl = gov_prtx_create_form_url($liveConfig);
            $out['url'] = function_exists('gov_edxdiag_safe_url') ? gov_edxdiag_safe_url($submitUrl) : $submitUrl;
            $out['create_url'] = function_exists('gov_edxdiag_safe_url') ? gov_edxdiag_safe_url($createUrl) : $createUrl;
            if ($createUrl === '') { $out['warnings'][] = 'edxeix_create_form_url_missing'; return $out; }

            $session = gov_edxdiag_load_session_raw($liveConfig);
            $cookie = trim((string)($session['cookie_header'] ?? ''));
            $sessionCsrf = trim((string)($session['csrf_token'] ?? ''));
            if ($sessionCsrf !== '') { $out['session_csrf_hash_16'] = substr(hash('sha256', $sessionCsrf), 0, 16); }
            if ($cookie === '') { $out['warnings'][] = 'session_cookie_missing'; return $out; }

            $timeout = (int)($liveConfig['curl_timeout_seconds'] ?? 45);
            $timeout = max(10, min(120, $timeout));
            $url = $createUrl;
            $referer = $submitUrl;
            $body = '';
            $finalUrl = $createUrl;
            for ($i = 0; $i < 6; $i++) {
                $step = gov_prtx_form_get_step($url, $cookie, $timeout, $referer);
                $body = (string)$step['body'];
                $status = (int)$step['status'];
                $finalUrl = $url;
                $safeStep = $step;
                unset($safeStep['body'], $safeStep['location_raw']);
                $out['steps'][] = $safeStep;
                $out['performed'] = true;
                $out['status'] = $status;
                $out['body_fingerprint'] = $step['body_fingerprint'];
                $out['final_url'] = function_exists('gov_edxdiag_safe_url') ? gov_edxdiag_safe_url($finalUrl) : $finalUrl;
                $locationRaw = trim((string)($step['location_raw'] ?? ''));
                if ($status >= 300 && $status < 400 && $locationRaw !== '' && function_exists('gov_edxdiag_resolve_url')) {
                    $url = gov_edxdiag_resolve_url($url, $locationRaw);
                    $referer = $finalUrl;
                    continue;
                }
                break;
            }
            $out['redirect_count'] = max(0, count($out['steps']) - 1);

            $token = gov_prtx_extract_form_token($body);
            $out['token_present'] = $token !== '';
            if ($token !== '') {
                $out['token_hash_16'] = substr(hash('sha256', $token), 0, 16);
                $out['fresh_token_available_for_transport'] = true;
                if ($includeInternalToken) {
                    $out['__internal_form_token'] = $token;
                    $out['__internal_response_body'] = $body;
                }
            }
            $out['token_matches_session_csrf'] = $token !== '' && $sessionCsrf !== '' && hash_equals($token, $sessionCsrf);
            $out['form_summary'] = gov_prtx_extract_form_summary($body, $finalUrl);
            $signals = is_array($out['body_fingerprint']['signals'] ?? null) ? $out['body_fingerprint']['signals'] : [];
            $out['create_form_context_ready'] = gov_prtx_create_form_context_ready($out);
            $out['ok'] = !empty($out['create_form_context_ready'])
                && $out['token_present']
                && $out['token_matches_session_csrf'];

            if (!$out['token_present']) { $out['warnings'][] = 'form_token_not_found'; }
            if (empty($out['form_summary']['form_present'])) { $out['warnings'][] = 'create_form_not_found'; }
            if (!empty($out['form_summary']['required_expected_fields_missing'])) { $out['warnings'][] = 'create_form_required_fields_missing'; }
            if ($out['token_present'] && !$out['token_matches_session_csrf']) { $out['warnings'][] = 'form_token_differs_from_saved_session_csrf'; }
            if ($out['status'] >= 300 && $out['status'] < 400) { $out['warnings'][] = 'create_form_final_response_is_redirect'; }
            if (empty($out['create_form_context_ready'])) {
                if (!empty($signals['login'])) { $out['warnings'][] = 'create_form_page_has_login_text_signal'; }
                if (!empty($signals['csrf'])) { $out['warnings'][] = 'create_form_page_has_csrf_text_signal'; }
            } else {
                if (!empty($signals['login']) || !empty($signals['csrf'])) {
                    $out['warnings'][] = 'content_text_signals_present_but_authenticated_create_form_ready';
                }
            }
            return $out;
        } catch (Throwable $e) {
            $out['warnings'][] = 'form_token_diagnostic_exception: ' . $e->getMessage();
            return $out;
        }
    }
}

if (!function_exists('gov_prtx_sanitize_form_token_diagnostic')) {
    /** @param array<string,mixed> $diag @return array<string,mixed> */
    function gov_prtx_sanitize_form_token_diagnostic(array $diag): array
    {
        unset($diag['__internal_form_token'], $diag['__internal_response_body']);
        $diag['raw_token_printed'] = false;
        $diag['raw_cookie_printed'] = false;
        $diag['raw_body_printed'] = false;
        return $diag;
    }
}


if (!function_exists('gov_prtx_extract_checked_or_first_input_value')) {
    function gov_prtx_extract_checked_or_first_input_value(string $html, string $name): string
    {
        $found = '';
        if (!preg_match_all('/<input\b[^>]*>/i', $html, $matches)) { return ''; }
        foreach ($matches[0] as $tag) {
            $tag = (string)$tag;
            if (gov_prtx_html_attr($tag, 'name') !== $name) { continue; }
            $value = gov_prtx_html_attr($tag, 'value');
            if ($found === '') { $found = $value; }
            if (preg_match('/\bchecked\b/i', $tag)) { return $value; }
        }
        return $found;
    }
}

if (!function_exists('gov_prtx_payload_customer_name')) {
    function gov_prtx_payload_customer_name(array $rehearsal, array $payload): string
    {
        $candidate = is_array($rehearsal['readiness_packet']['candidate'] ?? null) ? $rehearsal['readiness_packet']['candidate'] : [];
        $name = trim((string)($candidate['customer_name'] ?? ''));
        if ($name !== '') { return $name; }
        $lessee = trim((string)($payload['lessee'] ?? ''));
        if ($lessee !== '') {
            $parts = preg_split('/\s*\/\s*/', $lessee);
            return trim((string)($parts[0] ?? $lessee));
        }
        return '';
    }
}

if (!function_exists('gov_prtx_align_payload_to_create_form')) {
    /** @param array<string,mixed> $payload @param array<string,mixed> $rehearsal @param array<string,mixed> $formDiagInternal @return array<string,mixed> */
    function gov_prtx_align_payload_to_create_form(array $payload, array $rehearsal, array $formDiagInternal): array
    {
        $body = (string)($formDiagInternal['__internal_response_body'] ?? '');
        $out = $payload;
        $customerName = gov_prtx_payload_customer_name($rehearsal, $payload);
        $lesseeType = gov_prtx_extract_checked_or_first_input_value($body, 'lessee[type]');
        if ($lesseeType === '') {
            // Safe browser-form fallback. The live form normally has a checked radio; this is only used if parsing fails.
            $lesseeType = 'natural';
        }
        $out['lessee[type]'] = $lesseeType;
        $out['lessee[name]'] = $customerName !== '' ? $customerName : (string)($payload['lessee'] ?? '');
        $out['lessee[vat_number]'] = '';
        $out['lessee[legal_representative]'] = '';
        unset($out['lessee']);
        // Browser form posts starting_point_id, not the legacy helper alias starting_point.
        if (isset($out['starting_point_id'])) { unset($out['starting_point']); }
        return $out;
    }
}

if (!function_exists('gov_prtx_summarize_transport_payload')) {
    /** @param array<string,mixed> $payload @return array<string,mixed> */
    function gov_prtx_summarize_transport_payload(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);
        return [
            'field_count' => count($payload),
            'fields' => $keys,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'lessee_mode' => isset($payload['lessee[name]']) ? 'edxeix_nested_lessee_fields' : (isset($payload['lessee']) ? 'legacy_flat_lessee' : 'none'),
            'starting_point_mode' => isset($payload['starting_point_id']) ? 'starting_point_id' : (isset($payload['starting_point']) ? 'legacy_starting_point' : 'none'),
        ];
    }
}

if (!function_exists('gov_prtx_validation_text_lines')) {
    /** @return array<int,string> */
    function gov_prtx_validation_text_lines(string $body): array
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $body) ?? $body;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<(div|p|li|span|small|strong|label|br|ul|ol|tr|td|th)\b[^>]*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\r\n|\r/', "\n", $text) ?? $text;
        $lines = preg_split('/\n+/', $text) ?: [];
        $keep = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', (string)$line) ?? (string)$line);
            if ($line === '' || strlen($line) < 3) { continue; }
            $lower = function_exists('mb_strtolower') ? mb_strtolower($line, 'UTF-8') : strtolower($line);
            if (preg_match('/(required|invalid|error|failed|must|cannot|υποχρεω|σφάλ|λαθος|λάθος|δεν|παρακαλ|πρέπει|λήξει|εληξε|έληξε)/iu', $lower)) {
                $keep[] = mb_substr($line, 0, 220, 'UTF-8');
            }
            if (count($keep) >= 30) { break; }
        }
        return array_values(array_unique($keep));
    }
}

if (!function_exists('gov_prtx_trace_validation_summary')) {
    /** @param array<string,mixed> $trace @return array<string,mixed> */
    function gov_prtx_trace_validation_summary(array $trace): array
    {
        $steps = is_array($trace['steps'] ?? null) ? $trace['steps'] : [];
        $first = $steps ? $steps[0] : [];
        $last = $steps ? $steps[count($steps) - 1] : [];
        $firstStatus = (int)($first['status'] ?? 0);
        $firstLocation = (string)($first['location'] ?? '');
        $finalUrl = (string)($last['url'] ?? '');
        $finalBody = (string)($trace['__internal_final_body'] ?? '');
        $returnedToCreate = $firstStatus >= 300 && $firstStatus < 400
            && strpos($firstLocation, '/dashboard/lease-agreement/create') !== false
            && strpos($finalUrl, '/dashboard/lease-agreement/create') !== false;
        $lines = $finalBody !== '' ? gov_prtx_validation_text_lines($finalBody) : [];
        return [
            'returned_to_create_form' => $returnedToCreate,
            'likely_validation_return' => $returnedToCreate,
            'validation_text_count' => count($lines),
            'validation_text_lines' => $lines,
            'raw_body_printed' => false,
            'note' => $returnedToCreate
                ? 'POST redirected back to the create form. This usually means validation failed or the server did not accept one or more fields.'
                : 'No create-form return pattern detected.',
        ];
    }
}

if (!function_exists('gov_prtx_curl_step_internal')) {
    /** @return array<string,mixed> */
    function gov_prtx_curl_step_internal(string $method, string $url, array $payload, array $session, int $timeout, string $referer = ''): array
    {
        $cookie = trim((string)($session['cookie_header'] ?? ''));
        if ($cookie === '') { throw new RuntimeException('EDXEIX session cookie header is missing.'); }
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'User-Agent: gov.cabnet.app EDXEIX strict identity trace v3.2.37',
            'Cookie: ' . $cookie,
        ];
        if ($referer !== '') { $headers[] = 'Referer: ' . $referer; }
        $ch = curl_init();
        if (!$ch) { throw new RuntimeException('Unable to initialize cURL.'); }
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
            throw new RuntimeException('EDXEIX strict trace cURL error: ' . $error . ' (' . $errno . ')');
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
            '_body' => $body,
        ];
    }
}

if (!function_exists('gov_prtx_trace_transport_with_fresh_form_token')) {
    /**
     * Perform a sanitized transport trace with a freshly fetched create-form token.
     * v3.2.37 uses an internal trace step so validation text can be summarized
     * without exposing raw HTML, cookies, CSRF tokens, or the raw form token.
     *
     * @param array<string,mixed> $liveConfig
     * @param array<string,mixed> $sessionRaw
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $formDiagInternal
     * @return array<string,mixed>
     */
    function gov_prtx_trace_transport_with_fresh_form_token(array $liveConfig, array $sessionRaw, array $payload, array $formDiagInternal, bool $followRedirects, int $maxRedirects): array
    {
        $freshToken = trim((string)($formDiagInternal['__internal_form_token'] ?? ''));
        if ($freshToken === '') { throw new RuntimeException('Fresh EDXEIX create-form token is unavailable for transport.'); }
        $url = trim((string)($liveConfig['edxeix_submit_url'] ?? ''));
        if ($url === '') { throw new RuntimeException('EDXEIX submit URL is missing.'); }
        $timeout = (int)($liveConfig['curl_timeout_seconds'] ?? 45);
        $timeout = max(10, min(120, $timeout));
        $sessionRaw['csrf_token'] = $freshToken;
        $transportPayload = gov_live_prepare_transport_payload($payload, $sessionRaw);
        $method = 'POST';
        $steps = [];
        $currentUrl = $url;
        $referer = (string)($formDiagInternal['final_url'] ?? '');
        $finalBody = '';
        for ($i = 0; $i <= $maxRedirects; $i++) {
            $step = gov_prtx_curl_step_internal($method, $currentUrl, $method === 'POST' ? $transportPayload : [], $sessionRaw, $timeout, $referer);
            $rawLocation = (string)($step['_raw_location'] ?? '');
            $finalBody = (string)($step['_body'] ?? '');
            $safeStep = $step;
            unset($safeStep['_raw_location'], $safeStep['_body']);
            $steps[] = $safeStep;
            if (!$followRedirects || $rawLocation === '' || !in_array((int)$step['status'], [301, 302, 303, 307, 308], true)) { break; }
            $next = gov_edxdiag_resolve_url($currentUrl, $rawLocation);
            if ($next === '') { break; }
            $referer = $currentUrl;
            $currentUrl = $next;
            if (in_array((int)$step['status'], [301, 302, 303], true)) { $method = 'GET'; }
        }
        $trace = [
            'started_at' => gov_edxdiag_now(),
            'submit_url' => gov_edxdiag_safe_url($url),
            'follow_redirects' => $followRedirects,
            'max_redirects' => $maxRedirects,
            'payload_summary' => gov_prtx_summarize_transport_payload($payload),
            'steps' => $steps,
            '__internal_final_body' => $finalBody,
        ];
        $trace['validation_summary'] = gov_prtx_trace_validation_summary($trace);
        unset($trace['__internal_final_body']);
        return $trace;
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
        if (empty($formDiag['fresh_token_available_for_transport'])) {
            $blockers[] = 'fresh_create_form_token_unavailable_for_transport';
        }
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
        // v3.2.37 strict identity lock: every transport must explicitly match the intended candidate.
        foreach (gov_prtx_identity_blockers(gov_prtx_identity_lock_report($options, $rehearsal)) as $identityBlocker) {
            $blockers[] = $identityBlocker;
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
        $formTokenDiagnosticInternal = gov_prtx_form_token_diagnostic(true);
        $formTokenDiagnostic = gov_prtx_sanitize_form_token_diagnostic($formTokenDiagnosticInternal);
        $preTransportHoldBlockers = gov_prtx_pre_transport_hold_blockers($rehearsal, $formTokenDiagnostic);
        $identityLock = gov_prtx_identity_lock_report($options, $rehearsal);
        $blockers = gov_prtx_transport_blockers($options, $rehearsal);
        if ($transportRequested) { $blockers = array_values(array_unique(array_merge($blockers, $preTransportHoldBlockers))); }
        $packet = is_array($rehearsal['operator_rehearsal_packet'] ?? null) ? $rehearsal['operator_rehearsal_packet'] : [];
        $payload = is_array($packet['payload_preview'] ?? null) ? $packet['payload_preview'] : [];
        $transportPayload = gov_prtx_align_payload_to_create_form($payload, $rehearsal, $formTokenDiagnosticInternal);
        $transportPayloadSummary = gov_prtx_summarize_transport_payload($transportPayload);
        $trace = null;
        $classification = [
            'code' => !empty($rehearsal['ready_for_later_supervised_transport_patch']) ? 'PRE_RIDE_TRANSPORT_TRACE_HELD_FOR_SESSION_REFRESH' : 'PRE_RIDE_TRANSPORT_TRACE_BLOCKED',
            'message' => !empty($rehearsal['ready_for_later_supervised_transport_patch'])
                ? 'Candidate is structurally ready, but server-side transport is held by closure/retry prevention or create-form token diagnostics. No submit was performed.'
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
                    $trace = gov_prtx_trace_transport_with_fresh_form_token($liveConfig, $sessionRaw, $transportPayload, $formTokenDiagnosticInternal, $followRedirects, 6);
                    $validationSummary = is_array($trace['validation_summary'] ?? null) ? $trace['validation_summary'] : [];
                    if (!empty($validationSummary['returned_to_create_form'])) {
                        $traceClass = ['code' => 'SUBMIT_REDIRECT_CREATE_FORM_RETURNED', 'message' => 'POST redirected back to the EDXEIX create form. Treat as not confirmed/suspected validation return until manual list verification.'];
                    } else {
                        $traceClass = gov_edxdiag_classify_trace($trace);
                    }
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
            'version' => 'v3.2.37-strict-identity-lock-validation-capture',
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
                'fresh_create_form_token_printed' => false,
                'fresh_create_form_token_stored' => false,
                'fresh_create_form_token_used_only_when_transport_performed' => $trace !== null,
            ],
            'transport_blockers' => array_values(array_unique($blockers)),
            'pre_transport_hold_blockers' => $preTransportHoldBlockers,
            'form_token_diagnostic' => $formTokenDiagnostic,
            'identity_lock' => $identityLock,
            'transport_payload_summary' => $transportPayloadSummary,
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
                : 'Review blockers/form-token diagnostic and identity lock. Only perform a supervised one-shot POST for a new future candidate that is not manually closed, has no previous server attempt, and has explicit customer/driver/vehicle/pickup identity expectations plus hash/phrase confirmation.',
        ];

        if ($transportRequested) {
            $insert = gov_prtx_attempt_insert($result);
            $result['attempt_metadata'] = array_replace($result['attempt_metadata'], $insert);
        }

        return $result;
    }
}
