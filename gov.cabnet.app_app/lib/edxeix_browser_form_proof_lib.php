<?php
/**
 * gov.cabnet.app — EDXEIX browser create-form proof validator.
 * v3.2.34
 *
 * Purpose:
 * - Validate sanitized JSON copied from the logged-in EDXEIX browser page.
 * - Confirm whether the browser can see the real create form and hidden token.
 * - Do not store or print cookies, CSRF token values, raw HTML, or form values.
 *
 * Safety contract:
 * - No EDXEIX HTTP request.
 * - No EDXEIX POST.
 * - No AADE/myDATA call.
 * - No queue job.
 * - No normalized_bookings write.
 * - No live config write.
 */

declare(strict_types=1);

if (!function_exists('gov_bfp_expected_fields')) {
    /** @return list<string> */
    function gov_bfp_expected_fields(): array
    {
        return [
            'lessor',
            'driver',
            'vehicle',
            'starting_point',
            'boarding_point',
            'disembark_point',
            'lessee',
            'started_at',
            'ended_at',
            'price',
        ];
    }
}

if (!function_exists('gov_bfp_safe_string')) {
    function gov_bfp_safe_string($value, int $max = 500): string
    {
        $value = trim((string)$value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }
        return substr($value, 0, $max);
    }
}

if (!function_exists('gov_bfp_is_assoc')) {
    function gov_bfp_is_assoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}

if (!function_exists('gov_bfp_decode_json')) {
    /** @return array<string,mixed> */
    function gov_bfp_decode_json(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            throw new InvalidArgumentException('Proof JSON is empty.');
        }
        if (strlen($json) > 200000) {
            throw new InvalidArgumentException('Proof JSON is too large.');
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Proof JSON could not be decoded.');
        }
        if (!gov_bfp_is_assoc($decoded)) {
            throw new InvalidArgumentException('Proof JSON must be an object.');
        }
        return $decoded;
    }
}

if (!function_exists('gov_bfp_array_strings')) {
    /** @return list<string> */
    function gov_bfp_array_strings($value, int $maxItems = 120): array
    {
        if (!is_array($value)) { return []; }
        $out = [];
        foreach ($value as $item) {
            $s = gov_bfp_safe_string($item, 120);
            if ($s !== '') { $out[] = $s; }
            if (count($out) >= $maxItems) { break; }
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('gov_bfp_collect_keys')) {
    /** @return list<string> */
    function gov_bfp_collect_keys($value, string $prefix = '', int $depth = 0): array
    {
        if ($depth > 8 || !is_array($value)) { return []; }
        $keys = [];
        foreach ($value as $k => $v) {
            $key = $prefix === '' ? (string)$k : $prefix . '.' . (string)$k;
            $keys[] = $key;
            if (is_array($v)) {
                $keys = array_merge($keys, gov_bfp_collect_keys($v, $key, $depth + 1));
            }
        }
        return $keys;
    }
}

if (!function_exists('gov_bfp_secret_warnings')) {
    /** @return list<string> */
    function gov_bfp_secret_warnings(array $proof): array
    {
        $warnings = [];
        $keys = gov_bfp_collect_keys($proof);
        foreach ($keys as $key) {
            $lower = strtolower($key);
            // token_hash_16 and session_csrf_hash_16 are allowed because they are hashes only.
            if (str_ends_with($lower, 'token_hash_16') || str_ends_with($lower, 'csrf_hash_16') || str_contains($lower, 'hash')) {
                continue;
            }
            foreach (['cookie', 'csrf', 'xsrf', 'token_value', 'raw_token', 'authorization', 'bearer', 'password', 'secret', 'innerhtml', 'outerhtml', 'body_html', 'html_body'] as $needle) {
                if (str_contains($lower, $needle)) {
                    $warnings[] = 'potential_secret_or_raw_field_key_detected:' . $key;
                    break;
                }
            }
        }
        return array_values(array_unique($warnings));
    }
}

if (!function_exists('gov_bfp_normalize_url_host')) {
    function gov_bfp_normalize_url_host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return strtolower((string)$host);
    }
}

if (!function_exists('gov_bfp_path')) {
    function gov_bfp_path(string $url): string
    {
        return (string)(parse_url($url, PHP_URL_PATH) ?: '');
    }
}

if (!function_exists('gov_bfp_validate_proof_array')) {
    /** @param array<string,mixed> $proof @return array<string,mixed> */
    function gov_bfp_validate_proof_array(array $proof): array
    {
        $version = gov_bfp_safe_string($proof['version'] ?? '');
        $pageUrl = gov_bfp_safe_string($proof['page_url'] ?? $proof['url'] ?? '', 1000);
        $pageHost = gov_bfp_normalize_url_host($pageUrl);
        $pagePath = gov_bfp_path($pageUrl);
        $finalUrl = gov_bfp_safe_string($proof['final_url'] ?? $pageUrl, 1000);
        $formPresent = !empty($proof['form_present']);
        $tokenPresent = !empty($proof['token_present']);
        $tokenHash16 = gov_bfp_safe_string($proof['token_hash_16'] ?? '');
        $formAction = gov_bfp_safe_string($proof['form_action_safe'] ?? $proof['form_action'] ?? '', 1000);
        $formMethod = strtoupper(gov_bfp_safe_string($proof['form_method'] ?? ''));
        $inputNames = gov_bfp_array_strings($proof['input_names'] ?? []);
        $selectNames = gov_bfp_array_strings($proof['select_names'] ?? []);
        $textareaNames = gov_bfp_array_strings($proof['textarea_names'] ?? []);
        $hiddenNames = gov_bfp_array_strings($proof['hidden_names'] ?? []);
        $allNames = array_values(array_unique(array_merge($inputNames, $selectNames, $textareaNames, $hiddenNames)));

        $expected = gov_bfp_expected_fields();
        $present = [];
        $missing = [];
        foreach ($expected as $field) {
            if (in_array($field, $allNames, true)) { $present[] = $field; }
            else { $missing[] = $field; }
        }

        $warnings = [];
        $blockers = [];
        if ($version === '' || !str_starts_with($version, 'v3.2.34-browser-create-form-proof')) {
            $warnings[] = 'proof_version_not_v3_2_34';
        }
        if ($pageHost !== 'edxeix.yme.gov.gr') {
            $blockers[] = 'proof_not_from_edxeix_yme_gov_gr';
        }
        if (!str_contains($pagePath, '/dashboard/lease-agreement/create')) {
            $warnings[] = 'proof_not_from_create_path';
        }
        if (!$formPresent) { $blockers[] = 'browser_create_form_not_present'; }
        if (!$tokenPresent || $tokenHash16 === '') { $blockers[] = 'browser_form_token_not_present'; }
        if ($formMethod !== '' && $formMethod !== 'POST') { $warnings[] = 'browser_form_method_not_post'; }
        if ($missing) { $warnings[] = 'expected_fields_missing:' . implode(',', $missing); }
        $secretWarnings = gov_bfp_secret_warnings($proof);
        if ($secretWarnings) {
            $blockers = array_merge($blockers, $secretWarnings);
        }

        $ready = empty($blockers) && empty($missing);
        return [
            'ok' => true,
            'version' => 'v3.2.34-browser-create-form-proof-validator',
            'classification' => [
                'code' => $ready ? 'BROWSER_CREATE_FORM_PROOF_READY' : 'BROWSER_CREATE_FORM_PROOF_NOT_READY',
                'message' => $ready
                    ? 'Browser proof confirms the logged-in browser can see the EDXEIX create form and hidden token. No POST was performed.'
                    : 'Browser proof is not ready for automation. Review blockers/warnings. No POST was performed.',
            ],
            'ready_for_browser_assisted_next_step' => $ready,
            'safety' => [
                'edxeix_http_request_from_server' => false,
                'edxeix_post' => false,
                'aade_call' => false,
                'queue_job' => false,
                'normalized_booking_write' => false,
                'live_config_write' => false,
                'raw_cookie_printed' => false,
                'raw_csrf_printed' => false,
                'raw_token_printed_or_stored' => false,
                'raw_body_printed_or_stored' => false,
            ],
            'proof_summary' => [
                'proof_version' => $version,
                'captured_at' => gov_bfp_safe_string($proof['captured_at'] ?? ''),
                'page_url' => $pageUrl,
                'page_host' => $pageHost,
                'page_path' => $pagePath,
                'final_url' => $finalUrl,
                'form_present' => $formPresent,
                'form_method' => $formMethod,
                'form_action_safe' => $formAction,
                'token_present' => $tokenPresent,
                'token_hash_16' => $tokenHash16,
                'field_counts' => [
                    'input_count' => count($inputNames),
                    'select_count' => count($selectNames),
                    'textarea_count' => count($textareaNames),
                    'hidden_count' => count($hiddenNames),
                    'all_names_count' => count($allNames),
                ],
                'expected_fields_present' => $present,
                'expected_fields_missing' => $missing,
                'input_names' => $inputNames,
                'select_names' => $selectNames,
                'textarea_names' => $textareaNames,
                'hidden_names' => $hiddenNames,
            ],
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'next_action' => $ready
                ? 'Use this proof to build the next browser-assisted no-secret fill/submit design. Do not attempt server-side POST with stale session cookies.'
                : 'Open the real EDXEIX create form in the logged-in browser, run the v3.2.34 proof snippet, and paste the sanitized JSON here.',
        ];
    }
}

if (!function_exists('gov_bfp_validate_json')) {
    /** @return array<string,mixed> */
    function gov_bfp_validate_json(string $json): array
    {
        return gov_bfp_validate_proof_array(gov_bfp_decode_json($json));
    }
}
