<?php
/**
 * gov.cabnet.app — Pre-ride readiness watch helper.
 * v3.2.28
 *
 * Purpose:
 * - Check the latest Bolt pre-ride Maildir email.
 * - Optionally capture sanitized metadata only when the candidate is ready.
 * - Produce the existing one-shot readiness packet for the captured/latest candidate.
 *
 * Safety:
 * - No EDXEIX HTTP transport.
 * - No AADE/myDATA call.
 * - No queue job.
 * - No normalized_bookings write.
 * - Optional write is limited to sanitized edxeix_pre_ride_candidates metadata.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php';
require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php';

if (!function_exists('gov_prw_bool')) {
    function gov_prw_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gov_prw_pick_candidate_id')) {
    /**
     * @param array<string,mixed> $candidateRun
     */
    function gov_prw_pick_candidate_id(array $candidateRun): int
    {
        $write = is_array($candidateRun['write'] ?? null) ? $candidateRun['write'] : [];
        $candidateId = (int)($write['candidate_id'] ?? 0);
        return $candidateId > 0 ? $candidateId : 0;
    }
}

if (!function_exists('gov_prw_status_from_parts')) {
    /**
     * @param array<string,mixed> $candidateRun
     * @param array<string,mixed>|null $readinessPacket
     */
    function gov_prw_status_from_parts(array $candidateRun, ?array $readinessPacket, bool $captureRequested): array
    {
        $candidateCode = (string)($candidateRun['classification']['code'] ?? 'UNKNOWN');
        $readyCandidate = $candidateCode === 'PRE_RIDE_READY_CANDIDATE'
            && !empty($candidateRun['candidate']['ready_for_edxeix']);
        $capturedId = gov_prw_pick_candidate_id($candidateRun);
        $packetReady = is_array($readinessPacket) && !empty($readinessPacket['ready_for_supervised_one_shot']);
        $packetCode = is_array($readinessPacket) ? (string)($readinessPacket['classification']['code'] ?? '') : '';

        if ($captureRequested && $capturedId > 0 && $packetReady) {
            return [
                'code' => 'WATCH_CAPTURED_READY_PACKET',
                'message' => 'Latest pre-ride email was ready, sanitized metadata was captured, and the one-shot readiness packet is ready. No submit was performed.',
            ];
        }

        if ($captureRequested && $capturedId > 0 && !$packetReady) {
            return [
                'code' => 'WATCH_CAPTURED_PACKET_BLOCKED',
                'message' => 'Latest pre-ride metadata was captured, but the one-shot readiness packet is blocked. No submit was performed.',
            ];
        }

        if ($readyCandidate && !$captureRequested) {
            return [
                'code' => 'WATCH_READY_NOT_CAPTURED',
                'message' => 'Latest pre-ride email is ready in dry-run. Capture sanitized metadata before preparing a supervised one-shot packet.',
            ];
        }

        if ($packetReady) {
            return [
                'code' => 'WATCH_EXISTING_READY_PACKET',
                'message' => 'An existing captured pre-ride candidate still has a ready one-shot packet. No submit was performed.',
            ];
        }

        if ($candidateCode === 'NO_PRE_RIDE_EMAIL_SOURCE') {
            return [
                'code' => 'WATCH_NO_PRE_RIDE_EMAIL_SOURCE',
                'message' => 'No matching pre-ride email source was found. No submit was performed.',
            ];
        }

        return [
            'code' => 'WATCH_NO_READY_CANDIDATE',
            'message' => 'No ready pre-ride candidate is available right now. No submit was performed.',
        ];
    }
}

if (!function_exists('gov_prw_run')) {
    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    function gov_prw_run(array $options = []): array
    {
        $captureReady = gov_prw_bool($options['capture_ready'] ?? false);
        $debugSource = gov_prw_bool($options['debug_source'] ?? false);
        $includeLatestReady = gov_prw_bool($options['include_latest_ready'] ?? true);

        $candidateOptions = [
            'latest_mail' => true,
            'write' => $captureReady,
            'debug_source' => $debugSource,
        ];
        if (isset($options['debug_lines'])) {
            $candidateOptions['debug_lines'] = (int)$options['debug_lines'];
        }

        $candidateRun = gov_prc_run($candidateOptions);
        $capturedId = gov_prw_pick_candidate_id($candidateRun);
        $candidateReady = (string)($candidateRun['classification']['code'] ?? '') === 'PRE_RIDE_READY_CANDIDATE'
            && !empty($candidateRun['candidate']['ready_for_edxeix']);

        $readinessPacket = null;
        if ($capturedId > 0) {
            $readinessPacket = gov_pror_run(['candidate_id' => $capturedId]);
        } elseif ($includeLatestReady) {
            $readinessPacket = gov_pror_run(['latest_ready' => true]);
        }

        $classification = gov_prw_status_from_parts($candidateRun, is_array($readinessPacket) ? $readinessPacket : null, $captureReady);
        $readyForOperator = in_array($classification['code'], ['WATCH_CAPTURED_READY_PACKET', 'WATCH_EXISTING_READY_PACKET'], true);

        return [
            'ok' => true,
            'version' => 'v3.2.28-pre-ride-readiness-watch',
            'classification' => $classification,
            'capture_ready_requested' => $captureReady,
            'candidate_ready_in_latest_mail' => $candidateReady,
            'captured_candidate_id' => $capturedId ?: null,
            'ready_for_operator_review' => $readyForOperator,
            'transport_performed' => false,
            'safety' => [
                'edxeix_transport' => false,
                'aade_call' => false,
                'queue_job' => false,
                'normalized_booking_write' => false,
                'metadata_write_possible_only_when_capture_ready_requested' => true,
            ],
            'latest_mail_candidate_report' => $candidateRun,
            'one_shot_readiness_packet' => $readinessPacket,
            'next_action' => $readyForOperator
                ? 'Review the ready packet immediately while the candidate remains at least 30 minutes in the future. Do not submit unless a later supervised one-shot transport patch is explicitly approved.'
                : 'Keep watching for the next real future Bolt pre-ride email. Capture metadata only when the latest candidate is ready.',
        ];
    }
}
