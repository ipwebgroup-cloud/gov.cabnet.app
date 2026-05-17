<?php
/**
 * gov.cabnet.app — Pre-ride supervised transport rehearsal packet.
 * v3.2.29
 *
 * Purpose:
 * - Re-check a captured pre-ride candidate immediately before any future live work.
 * - Produce a copy-safe operator packet for a later supervised one-shot transport patch.
 * - Verify the payload, EDXEIX session readiness, duplicate state, and disabled live gates.
 *
 * Safety contract:
 * - No EDXEIX HTTP transport.
 * - No AADE/myDATA calls.
 * - No queue jobs are created.
 * - No normalized_bookings rows are created or changed.
 * - No live_submit.php/config file is written.
 * - This rehearsal must remain read-only and cannot submit by itself.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php';

if (!function_exists('gov_prt_bool')) {
    function gov_prt_bool($value): bool
    {
        if (is_bool($value)) { return $value; }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gov_prt_payload_hash')) {
    function gov_prt_payload_hash(array $payload): string
    {
        return hash('sha256', function_exists('gov_prc_json') ? gov_prc_json($payload) : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''));
    }
}

if (!function_exists('gov_prt_required_payload_fields')) {
    /** @return array<int,string> */
    function gov_prt_required_payload_fields(): array
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

if (!function_exists('gov_prt_payload_missing_fields')) {
    /** @return array<int,string> */
    function gov_prt_payload_missing_fields(array $payload): array
    {
        $missing = [];
        foreach (gov_prt_required_payload_fields() as $field) {
            if (!array_key_exists($field, $payload) || trim((string)$payload[$field]) === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}

if (!function_exists('gov_prt_safe_live_gate_summary')) {
    /** @return array<string,mixed> */
    function gov_prt_safe_live_gate_summary(): array
    {
        $summary = [
            'live_config_available' => false,
            'live_submit_enabled' => false,
            'http_submit_enabled' => false,
            'edxeix_session_connected' => false,
            'one_shot_lock_configured' => false,
            'submit_url_configured' => false,
            'session_ready' => false,
            'session_summary' => [],
            'warnings' => [],
        ];

        try {
            if (function_exists('gov_live_load_config')) {
                $liveConfig = gov_live_load_config();
                $summary['live_config_available'] = true;
                $summary['live_submit_enabled'] = !empty($liveConfig['live_submit_enabled']);
                $summary['http_submit_enabled'] = !empty($liveConfig['http_submit_enabled']);
                $summary['edxeix_session_connected'] = !empty($liveConfig['edxeix_session_connected']);
                $summary['one_shot_lock_configured'] = trim((string)($liveConfig['allowed_booking_id'] ?? '')) !== ''
                    || trim((string)($liveConfig['allowed_order_reference'] ?? '')) !== '';
                $summary['submit_url_configured'] = trim((string)($liveConfig['edxeix_submit_url'] ?? '')) !== '';
                if (function_exists('gov_live_session_state')) {
                    $session = gov_live_session_state($liveConfig);
                    $summary['session_summary'] = $session;
                    $summary['session_ready'] = !empty($session['ready']);
                }
            } else {
                $summary['warnings'][] = 'gov_live_load_config() is unavailable.';
            }
        } catch (Throwable $e) {
            $summary['warnings'][] = 'Live gate summary warning: ' . $e->getMessage();
        }

        return $summary;
    }
}

if (!function_exists('gov_prt_future_guard_expiry')) {
    function gov_prt_future_guard_expiry(string $pickupAt, int $guardMinutes): string
    {
        $ts = strtotime($pickupAt);
        if ($ts === false) { return ''; }
        return date('Y-m-d H:i:s', $ts - ($guardMinutes * 60));
    }
}

if (!function_exists('gov_prt_operator_checklist')) {
    /** @return array<int,string> */
    function gov_prt_operator_checklist(): array
    {
        return [
            'Confirm the candidate still shows PRE_RIDE_TRANSPORT_REHEARSAL_READY.',
            'Confirm pickup is still at least 30 minutes in the future.',
            'Confirm driver, vehicle, lessor, starting point, pickup, drop-off, time, and price.',
            'Confirm duplicate_success_detected is false.',
            'Confirm EDXEIX session_ready is true.',
            'Do not submit from this rehearsal packet. It is read-only evidence only.',
            'A later supervised one-shot transport patch must be explicitly approved before any HTTP POST.',
        ];
    }
}

if (!function_exists('gov_prt_run')) {
    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    function gov_prt_run(array $options = []): array
    {
        $candidateId = (int)($options['candidate_id'] ?? 0);
        $latestReady = $candidateId <= 0 ? gov_prt_bool($options['latest_ready'] ?? true) : false;

        $sourceOptions = [];
        if ($candidateId > 0) {
            $sourceOptions['candidate_id'] = $candidateId;
        } elseif ($latestReady) {
            $sourceOptions['latest_ready'] = true;
        }

        $readiness = gov_pror_run($sourceOptions);
        $packet = is_array($readiness['operator_packet'] ?? null) ? $readiness['operator_packet'] : [];
        $payload = is_array($packet['payload_preview'] ?? null) ? $packet['payload_preview'] : [];
        $payloadMissing = gov_prt_payload_missing_fields($payload);
        $liveSummary = gov_prt_safe_live_gate_summary();

        $blockers = [];
        if (empty($readiness['ready_for_supervised_one_shot'])) {
            $blockers[] = 'one_shot_readiness_packet_not_ready';
        }
        foreach (($readiness['readiness_blockers'] ?? []) as $blocker) {
            $blockers[] = 'readiness_' . (string)$blocker;
        }
        foreach (($readiness['candidate_safety_blockers'] ?? []) as $blocker) {
            $blockers[] = 'candidate_' . (string)$blocker;
        }
        foreach ($payloadMissing as $field) {
            $blockers[] = 'payload_missing_' . $field;
        }
        if (!empty($readiness['duplicate_check']['duplicate_success_detected'])) {
            $blockers[] = 'duplicate_success_detected';
        }
        if (empty($liveSummary['session_ready'])) {
            $blockers[] = 'edxeix_session_not_ready';
        }
        if (empty($liveSummary['submit_url_configured'])) {
            $blockers[] = 'edxeix_submit_url_missing';
        }
        if (!empty($liveSummary['live_submit_enabled']) || !empty($liveSummary['http_submit_enabled'])) {
            $blockers[] = 'live_gate_enabled_during_rehearsal_disable_before_rehearsal';
        }

        $blockers = array_values(array_unique($blockers));
        $payloadHash = $payload ? gov_prt_payload_hash($payload) : (string)($packet['payload_hash'] ?? '');
        $candidate = is_array($readiness['candidate'] ?? null) ? $readiness['candidate'] : [];
        $pickup = (string)($packet['pickup_datetime'] ?? ($candidate['pickup_datetime'] ?? ''));
        $guard = (int)($packet['guard_minutes'] ?? 30);
        $candidateIdOut = (string)($packet['candidate_id'] ?? ($candidate['candidate_id'] ?? ''));
        $sourceHash16 = (string)($packet['source_hash_16'] ?? substr((string)($candidate['source_hash'] ?? ''), 0, 16));
        $payloadHash16 = substr($payloadHash, 0, 16);
        $rehearsalId = 'PRTR-' . ($candidateIdOut !== '' ? $candidateIdOut : 'latest') . '-' . $sourceHash16 . '-' . $payloadHash16;

        $ready = empty($blockers);
        return [
            'ok' => true,
            'version' => 'v3.2.29-pre-ride-transport-rehearsal',
            'classification' => [
                'code' => $ready ? 'PRE_RIDE_TRANSPORT_REHEARSAL_READY' : 'PRE_RIDE_TRANSPORT_REHEARSAL_BLOCKED',
                'message' => $ready
                    ? 'Pre-ride candidate is ready for a later supervised one-shot transport trace. No submit was performed.'
                    : 'Pre-ride transport rehearsal is blocked. No submit was performed.',
            ],
            'source_mode' => (string)($readiness['source_mode'] ?? ''),
            'ready_for_later_supervised_transport_patch' => $ready,
            'transport_performed' => false,
            'config_written' => false,
            'safety' => [
                'edxeix_transport' => false,
                'aade_call' => false,
                'queue_job' => false,
                'normalized_booking_write' => false,
                'live_config_write' => false,
                'purpose' => 'read-only rehearsal packet only',
            ],
            'rehearsal_blockers' => $blockers,
            'payload_missing_fields' => $payloadMissing,
            'live_gate_summary' => $liveSummary,
            'readiness_packet' => $readiness,
            'operator_rehearsal_packet' => [
                'rehearsal_id' => $rehearsalId,
                'candidate_id' => $candidateIdOut,
                'readiness_packet_id' => (string)($packet['packet_id'] ?? ''),
                'source_hash_16' => $sourceHash16,
                'payload_hash' => $payloadHash,
                'payload_hash_16' => $payloadHash16,
                'pickup_datetime' => $pickup,
                'future_guard_minutes' => $guard,
                'future_guard_expires_at' => gov_prt_future_guard_expiry($pickup, $guard),
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
                'operator_checklist' => gov_prt_operator_checklist(),
            ],
            'approval_phrase_for_next_patch' => 'Sophion, prepare the supervised pre-ride one-shot EDXEIX transport trace patch. I understand this is for one real eligible future ride only.',
            'next_action' => $ready
                ? 'Readiness rehearsal is complete. Do not submit yet. Build the actual one-shot transport trace patch only after explicit live-test approval.'
                : 'Fix blockers or wait for the next future pre-ride candidate. No EDXEIX transport is allowed from this rehearsal packet.',
        ];
    }
}
