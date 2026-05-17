<?php
/**
 * gov.cabnet.app — Pre-ride EDXEIX one-shot readiness packet v3.2.31
 *
 * Purpose:
 * - Convert an already captured or latest pre-ride candidate into a locked readiness packet.
 * - Verify the candidate is still future-safe, mapped, non-excluded, and duplicate-safe.
 * - Produce copy-safe operator evidence for the next supervised one-shot step.
 *
 * Safety contract:
 * - No EDXEIX HTTP transport.
 * - No AADE/myDATA calls.
 * - No queue jobs are created.
 * - No normalized_bookings rows are created or changed.
 * - No server-only live_submit.php file is written.
 * - This packet is readiness evidence only; it cannot submit by itself.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php';
require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php';

if (!function_exists('gov_pror_bool')) {
    function gov_pror_bool($value): bool
    {
        if (is_bool($value)) { return $value; }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gov_pror_json_decode')) {
    /** @return mixed */
    function gov_pror_json_decode($value)
    {
        if (is_array($value)) { return $value; }
        $value = trim((string)$value);
        if ($value === '') { return []; }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('gov_pror_payload_hash')) {
    function gov_pror_payload_hash(array $payload): string
    {
        return hash('sha256', gov_prc_json($payload));
    }
}

if (!function_exists('gov_pror_table_exists')) {
    function gov_pror_table_exists(mysqli $db): bool
    {
        return function_exists('gov_bridge_table_exists') && gov_bridge_table_exists($db, 'edxeix_pre_ride_candidates');
    }
}

if (!function_exists('gov_pror_fetch_candidate_row')) {
    function gov_pror_fetch_candidate_row(mysqli $db, int $candidateId): ?array
    {
        if ($candidateId <= 0 || !gov_pror_table_exists($db)) { return null; }
        return gov_bridge_fetch_one($db, 'SELECT * FROM edxeix_pre_ride_candidates WHERE id = ? LIMIT 1', [$candidateId]);
    }
}

if (!function_exists('gov_pror_fetch_latest_ready_candidate_row')) {
    function gov_pror_fetch_latest_ready_candidate_row(mysqli $db): ?array
    {
        if (!gov_pror_table_exists($db)) { return null; }
        $guard = function_exists('gov_prc_effective_future_guard_minutes') ? gov_prc_effective_future_guard_minutes() : 30;
        $cutoff = date('Y-m-d H:i:s', time() + ($guard * 60));
        $sql = "SELECT * FROM edxeix_pre_ride_candidates
                WHERE ready_for_edxeix = 1
                  AND status = 'ready'
                  AND pickup_datetime IS NOT NULL
                  AND pickup_datetime >= ?";
        $params = [$cutoff];
        if (function_exists('gov_bridge_table_exists') && gov_bridge_table_exists($db, 'edxeix_pre_ride_candidate_closures')) {
            $sql .= " AND NOT EXISTS (
                        SELECT 1 FROM edxeix_pre_ride_candidate_closures c
                        WHERE c.candidate_id = edxeix_pre_ride_candidates.id
                           OR (c.source_hash <> '' AND c.source_hash = edxeix_pre_ride_candidates.source_hash)
                     )";
        }
        // v3.2.31: latest-ready means newest still-future, not the oldest historical ready row.
        $sql .= " ORDER BY id DESC, created_at DESC LIMIT 1";
        return gov_bridge_fetch_one($db, $sql, $params);
    }
}

if (!function_exists('gov_pror_candidate_from_row')) {
    /** @return array<string,mixed> */
    function gov_pror_candidate_from_row(array $row): array
    {
        $parsed = gov_pror_json_decode($row['parsed_fields_json'] ?? '');
        $payload = gov_pror_json_decode($row['payload_preview_json'] ?? '');
        $mapping = gov_pror_json_decode($row['mapping_status_json'] ?? '');
        $blockers = gov_pror_json_decode($row['safety_blockers_json'] ?? '');
        $warnings = gov_pror_json_decode($row['warnings_json'] ?? '');

        return [
            'candidate_id' => (string)($row['id'] ?? ''),
            'source_system' => 'bolt_pre_ride_email',
            'source_type' => (string)($row['source_type'] ?? ''),
            'source_label' => (string)($row['source_label'] ?? ''),
            'source_hash' => (string)($row['source_hash'] ?? ''),
            'source_mtime' => (string)($row['source_mtime'] ?? ''),
            'order_reference' => (string)($row['order_reference'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'readiness_status' => (string)($row['readiness_status'] ?? ''),
            'ready_for_edxeix' => ((string)($row['ready_for_edxeix'] ?? '0') === '1'),
            'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
            'estimated_end_datetime' => (string)($row['estimated_end_datetime'] ?? ''),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'customer_phone' => (string)($row['customer_phone'] ?? ''),
            'driver_name' => (string)($row['driver_name'] ?? ''),
            'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
            'pickup_address' => (string)($row['pickup_address'] ?? ''),
            'dropoff_address' => (string)($row['dropoff_address'] ?? ''),
            'price_amount' => (string)($row['price_amount'] ?? ''),
            'price_currency' => (string)($row['price_currency'] ?? ''),
            'parsed_fields' => is_array($parsed) ? $parsed : [],
            'payload_preview' => is_array($payload) ? $payload : [],
            'mapping' => is_array($mapping) ? $mapping : [],
            'safety_blockers' => is_array($blockers) ? $blockers : [],
            'warnings' => is_array($warnings) ? $warnings : [],
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('gov_pror_source_candidate')) {
    /** @param array<string,mixed> $options @return array<string,mixed> */
    function gov_pror_source_candidate(mysqli $db, array $options): array
    {
        $candidateId = (int)($options['candidate_id'] ?? 0);
        if ($candidateId > 0) {
            $row = gov_pror_fetch_candidate_row($db, $candidateId);
            if (!is_array($row)) {
                return [
                    'ok' => false,
                    'source_mode' => 'candidate_id',
                    'candidate' => null,
                    'message' => 'No pre-ride candidate row found for candidate_id=' . $candidateId . '.',
                ];
            }
            return [
                'ok' => true,
                'source_mode' => 'candidate_id',
                'candidate' => gov_pror_candidate_from_row($row),
                'message' => 'Loaded captured candidate_id=' . $candidateId . '.',
            ];
        }

        if (!empty($options['latest_ready'])) {
            $row = gov_pror_fetch_latest_ready_candidate_row($db);
            if (!is_array($row)) {
                return [
                    'ok' => false,
                    'source_mode' => 'latest_ready',
                    'candidate' => null,
                    'message' => 'No captured ready pre-ride candidate exists in edxeix_pre_ride_candidates.',
                ];
            }
            return [
                'ok' => true,
                'source_mode' => 'latest_ready',
                'candidate' => gov_pror_candidate_from_row($row),
                'message' => 'Loaded latest captured ready pre-ride candidate.',
            ];
        }

        if (!empty($options['latest_mail'])) {
            $report = gov_prc_run([
                'latest_mail' => true,
                'write' => false,
                'debug_source' => false,
            ]);
            $candidate = is_array($report['candidate'] ?? null) ? $report['candidate'] : null;
            if (!is_array($candidate)) {
                return [
                    'ok' => false,
                    'source_mode' => 'latest_mail_dry_run',
                    'candidate' => null,
                    'message' => 'Latest mail did not produce a pre-ride candidate.',
                    'pre_ride_candidate_report' => $report,
                ];
            }
            $candidate['candidate_id'] = '';
            return [
                'ok' => true,
                'source_mode' => 'latest_mail_dry_run',
                'candidate' => $candidate,
                'message' => 'Loaded latest Maildir candidate in dry-run mode. Metadata was not written.',
                'pre_ride_candidate_report' => $report,
            ];
        }

        return [
            'ok' => false,
            'source_mode' => 'none',
            'candidate' => null,
            'message' => 'Select --candidate-id=N, --latest-ready=1, or --latest-mail=1.',
        ];
    }
}

if (!function_exists('gov_pror_duplicate_success_state')) {
    /** @return array<string,mixed> */
    function gov_pror_duplicate_success_state(mysqli $db, string $payloadHash): array
    {
        $out = [
            'payload_hash' => $payloadHash,
            'live_audit_success_count' => 0,
            'submission_attempt_success_count' => 0,
            'duplicate_success_detected' => false,
            'warnings' => [],
        ];
        if ($payloadHash === '') { return $out; }

        try {
            if (function_exists('gov_bridge_table_exists') && gov_bridge_table_exists($db, 'edxeix_live_submission_audit')) {
                $cols = gov_bridge_table_columns($db, 'edxeix_live_submission_audit');
                if (isset($cols['payload_hash'])) {
                    $successSql = isset($cols['success']) ? ' AND success = 1' : '';
                    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM edxeix_live_submission_audit WHERE payload_hash = ?' . $successSql, [$payloadHash]);
                    $out['live_audit_success_count'] = (int)($row['c'] ?? 0);
                }
            }
            if (function_exists('gov_bridge_table_exists') && gov_bridge_table_exists($db, 'submission_attempts')) {
                $cols = gov_bridge_table_columns($db, 'submission_attempts');
                if (isset($cols['payload_hash'])) {
                    $successParts = [];
                    if (isset($cols['success'])) { $successParts[] = 'success = 1'; }
                    if (isset($cols['remote_reference'])) { $successParts[] = "(remote_reference IS NOT NULL AND remote_reference <> '')"; }
                    if (isset($cols['response_status'])) { $successParts[] = '(response_status BETWEEN 200 AND 399)'; }
                    if (isset($cols['http_status'])) { $successParts[] = '(http_status BETWEEN 200 AND 399)'; }
                    $where = $successParts ? ' AND (' . implode(' OR ', $successParts) . ')' : '';
                    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts WHERE payload_hash = ?' . $where, [$payloadHash]);
                    $out['submission_attempt_success_count'] = (int)($row['c'] ?? 0);
                }
            }
        } catch (Throwable $e) {
            $out['warnings'][] = 'Duplicate success check warning: ' . $e->getMessage();
        }

        $out['duplicate_success_detected'] = ((int)$out['live_audit_success_count'] > 0) || ((int)$out['submission_attempt_success_count'] > 0);
        return $out;
    }
}

if (!function_exists('gov_pror_payload_missing_fields')) {
    /** @return array<int,string> */
    function gov_pror_payload_missing_fields(array $payload): array
    {
        $required = ['lessor', 'driver', 'vehicle', 'starting_point', 'boarding_point', 'disembark_point', 'lessee', 'started_at', 'ended_at', 'price'];
        $missing = [];
        foreach ($required as $key) {
            if (trim((string)($payload[$key] ?? '')) === '') { $missing[] = $key; }
        }
        return $missing;
    }
}

if (!function_exists('gov_pror_minutes_until')) {
    function gov_pror_minutes_until(string $datetime): ?int
    {
        $ts = strtotime($datetime);
        if ($ts === false) { return null; }
        return (int)floor(($ts - time()) / 60);
    }
}

if (!function_exists('gov_pror_live_gate_summary')) {
    /** @return array<string,mixed> */
    function gov_pror_live_gate_summary(): array
    {
        $liveConfig = function_exists('gov_live_load_config') ? gov_live_load_config() : [];
        $session = function_exists('gov_live_session_state') ? gov_live_session_state($liveConfig) : [];
        return [
            'live_submit_enabled' => !empty($liveConfig['live_submit_enabled']),
            'http_submit_enabled' => !empty($liveConfig['http_submit_enabled']),
            'edxeix_session_connected' => !empty($liveConfig['edxeix_session_connected']),
            'one_shot_lock_configured' => trim((string)($liveConfig['allowed_booking_id'] ?? '')) !== '' || trim((string)($liveConfig['allowed_order_reference'] ?? '')) !== '',
            'submit_url_configured' => trim((string)($liveConfig['edxeix_submit_url'] ?? '')) !== '',
            'session_ready' => !empty($session['ready']),
            'session_summary' => [
                'session_file_configured' => !empty($session['session_file_configured']),
                'session_file_exists' => !empty($session['session_file_exists']),
                'cookie_present' => !empty($session['cookie_present']),
                'csrf_present' => !empty($session['csrf_present']),
                'placeholder_detected' => !empty($session['placeholder_detected']),
                'updated_at' => (string)($session['updated_at'] ?? ''),
                'ready' => !empty($session['ready']),
            ],
        ];
    }
}

if (!function_exists('gov_pror_build_packet')) {
    /** @param array<string,mixed> $source @return array<string,mixed> */
    function gov_pror_build_packet(mysqli $db, array $source): array
    {
        $candidate = is_array($source['candidate'] ?? null) ? $source['candidate'] : [];
        if (!$candidate) {
            return [
                'ok' => true,
                'classification' => [
                    'code' => 'PRE_RIDE_ONE_SHOT_NO_CANDIDATE',
                    'message' => (string)($source['message'] ?? 'No pre-ride candidate was available.'),
                ],
                'source' => $source,
                'readiness_blockers' => ['no_candidate_available'],
                'transport_performed' => false,
                'next_action' => 'Capture or select a ready pre-ride candidate before preparing a one-shot readiness packet.',
            ];
        }

        $payload = is_array($candidate['payload_preview'] ?? null) ? $candidate['payload_preview'] : [];
        $mapping = is_array($candidate['mapping'] ?? null) ? $candidate['mapping'] : [];
        $candidateBlockers = is_array($candidate['safety_blockers'] ?? null) ? $candidate['safety_blockers'] : [];
        $payloadHash = gov_pror_payload_hash($payload);
        $duplicate = gov_pror_duplicate_success_state($db, $payloadHash);
        $closureState = function_exists('gov_prcl_closure_state')
            ? gov_prcl_closure_state($db, $candidate, $payload)
            : ['table_exists' => false, 'closed' => false, 'manual_submitted_v0' => false];
        $liveGate = gov_pror_live_gate_summary();
        $guard = function_exists('gov_prc_effective_future_guard_minutes') ? gov_prc_effective_future_guard_minutes() : 30;
        $pickupAt = trim((string)($candidate['pickup_datetime'] ?? ($payload['started_at'] ?? '')));
        $minutesUntilPickup = gov_pror_minutes_until($pickupAt);
        $futurePass = function_exists('gov_prc_future_guard_passes') ? gov_prc_future_guard_passes($pickupAt, $guard) : false;
        $missingPayload = gov_pror_payload_missing_fields($payload);

        $blockers = [];
        if (empty($candidate['ready_for_edxeix'])) { $blockers[] = 'candidate_not_marked_ready_for_edxeix'; }
        if ((string)($candidate['status'] ?? '') !== 'ready') { $blockers[] = 'candidate_status_not_ready'; }
        if (!empty($candidateBlockers)) { $blockers[] = 'candidate_has_safety_blockers'; }
        if ($pickupAt === '') { $blockers[] = 'candidate_missing_pickup_datetime'; }
        elseif (!$futurePass) { $blockers[] = 'candidate_pickup_not_' . $guard . '_min_future'; }
        if ($missingPayload) { $blockers[] = 'payload_missing_required_fields'; }
        if (empty($mapping['company_trusted_from_edxeix_mapping'])) { $blockers[] = 'lessor_not_trusted_from_edxeix_mapping'; }
        foreach (['lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id'] as $key) {
            if (trim((string)($mapping[$key] ?? '')) === '') { $blockers[] = 'mapping_missing_' . $key; }
        }
        if (!empty($duplicate['duplicate_success_detected'])) { $blockers[] = 'duplicate_success_detected_for_payload_hash'; }
        if (!empty($closureState['closed']) || !empty($closureState['manual_submitted_v0'])) { $blockers[] = 'candidate_closed_or_manually_submitted_via_v0'; }
        if (!empty($liveGate['live_submit_enabled']) || !empty($liveGate['http_submit_enabled'])) {
            $blockers[] = 'unexpected_live_submit_gate_enabled_for_readiness_packet';
        }

        $blockers = array_values(array_unique($blockers));
        $ready = empty($blockers);
        $packetId = 'PRC-' . ((string)($candidate['candidate_id'] ?? '') !== '' ? (string)$candidate['candidate_id'] : 'MAIL')
            . '-' . substr((string)($candidate['source_hash'] ?? ''), 0, 12)
            . '-' . substr($payloadHash, 0, 12);

        $operatorPacket = [
            'packet_id' => $packetId,
            'candidate_id' => (string)($candidate['candidate_id'] ?? ''),
            'source_hash_16' => substr((string)($candidate['source_hash'] ?? ''), 0, 16),
            'payload_hash' => $payloadHash,
            'payload_hash_16' => substr($payloadHash, 0, 16),
            'source_label' => (string)($candidate['source_label'] ?? ''),
            'pickup_datetime' => $pickupAt,
            'estimated_end_datetime' => (string)($candidate['estimated_end_datetime'] ?? ($payload['ended_at'] ?? '')),
            'minutes_until_pickup' => $minutesUntilPickup,
            'guard_minutes' => $guard,
            'lessor_id' => (string)($mapping['lessor_id'] ?? ($payload['lessor'] ?? '')),
            'driver_id' => (string)($mapping['driver_id'] ?? ($payload['driver'] ?? '')),
            'vehicle_id' => (string)($mapping['vehicle_id'] ?? ($payload['vehicle'] ?? '')),
            'starting_point_id' => (string)($mapping['starting_point_id'] ?? ($payload['starting_point'] ?? '')),
            'driver_name' => (string)($candidate['driver_name'] ?? ''),
            'vehicle_plate' => (string)($candidate['vehicle_plate'] ?? ''),
            'pickup_address' => (string)($candidate['pickup_address'] ?? ''),
            'dropoff_address' => (string)($candidate['dropoff_address'] ?? ''),
            'price_amount' => (string)($candidate['price_amount'] ?? ''),
            'price_currency' => (string)($candidate['price_currency'] ?? ''),
            'payload_preview' => $payload,
        ];

        return [
            'ok' => true,
            'classification' => [
                'code' => $ready ? 'PRE_RIDE_ONE_SHOT_READY_PACKET' : 'PRE_RIDE_ONE_SHOT_PACKET_BLOCKED',
                'message' => $ready
                    ? 'Pre-ride candidate is ready for the next supervised one-shot transport patch. No submit was performed.'
                    : 'Pre-ride one-shot readiness packet is blocked. No submit was performed.',
            ],
            'source_mode' => (string)($source['source_mode'] ?? ''),
            'source_message' => (string)($source['message'] ?? ''),
            'ready_for_supervised_one_shot' => $ready,
            'transport_performed' => false,
            'readiness_blockers' => $blockers,
            'payload_missing_fields' => $missingPayload,
            'candidate_safety_blockers' => $candidateBlockers,
            'duplicate_check' => $duplicate,
            'closure_state' => $closureState,
            'live_gate_summary' => $liveGate,
            'operator_packet' => $operatorPacket,
            'candidate' => $candidate,
            'pre_ride_candidate_report' => is_array($source['pre_ride_candidate_report'] ?? null) ? $source['pre_ride_candidate_report'] : null,
            'next_action' => $ready
                ? 'Review this readiness packet. No submit is performed here. v3.2.31 also blocks manually closed/V0-submitted candidates from retry.'
                : 'Fix the listed readiness blockers, mark manual V0 submissions closed, or capture a new future candidate. Do not attempt EDXEIX transport.',
        ];
    }
}

if (!function_exists('gov_pror_run')) {
    /** @param array<string,mixed> $options @return array<string,mixed> */
    function gov_pror_run(array $options = []): array
    {
        $db = gov_bridge_db();
        $source = gov_pror_source_candidate($db, $options);
        return gov_pror_build_packet($db, $source);
    }
}
