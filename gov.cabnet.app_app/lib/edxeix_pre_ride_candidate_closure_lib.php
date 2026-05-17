<?php
/**
 * gov.cabnet.app — Pre-ride candidate closure / retry prevention library.
 * v3.2.32
 *
 * Purpose:
 * - Mark a captured pre-ride candidate as manually submitted via V0/laptop.
 * - Prevent future server-side retry for the same candidate/source/payload.
 * - Keep V0 production untouched; this only writes diagnostic closure metadata.
 *
 * Safety contract:
 * - No EDXEIX HTTP transport.
 * - No AADE/myDATA call.
 * - No queue job.
 * - No normalized_bookings write.
 * - No live_submit.php config write.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php';

if (!function_exists('gov_prcl_now')) {
    function gov_prcl_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('gov_prcl_table_exists')) {
    function gov_prcl_table_exists(mysqli $db): bool
    {
        return function_exists('gov_bridge_table_exists') && gov_bridge_table_exists($db, 'edxeix_pre_ride_candidate_closures');
    }
}

if (!function_exists('gov_prcl_candidates_table_exists')) {
    function gov_prcl_candidates_table_exists(mysqli $db): bool
    {
        return function_exists('gov_bridge_table_exists') && gov_bridge_table_exists($db, 'edxeix_pre_ride_candidates');
    }
}

if (!function_exists('gov_prcl_json_decode')) {
    /** @return mixed */
    function gov_prcl_json_decode($value)
    {
        if (is_array($value)) { return $value; }
        $value = trim((string)$value);
        if ($value === '') { return []; }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('gov_prcl_payload_hash')) {
    function gov_prcl_payload_hash(array $payload): string
    {
        if (function_exists('gov_prc_json')) {
            return hash('sha256', gov_prc_json($payload));
        }
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('gov_prcl_fetch_candidate_row')) {
    function gov_prcl_fetch_candidate_row(mysqli $db, int $candidateId): ?array
    {
        if ($candidateId <= 0 || !gov_prcl_candidates_table_exists($db)) { return null; }
        return gov_bridge_fetch_one($db, 'SELECT * FROM edxeix_pre_ride_candidates WHERE id = ? LIMIT 1', [$candidateId]);
    }
}

if (!function_exists('gov_prcl_payload_from_candidate_row')) {
    /** @return array<string,mixed> */
    function gov_prcl_payload_from_candidate_row(array $row): array
    {
        $payload = gov_prcl_json_decode($row['payload_preview_json'] ?? '');
        return is_array($payload) ? $payload : [];
    }
}

if (!function_exists('gov_prcl_fetch_closure')) {
    /** @return array<string,mixed>|null */
    function gov_prcl_fetch_closure(mysqli $db, int $candidateId = 0, string $sourceHash = '', string $payloadHash = ''): ?array
    {
        if (!gov_prcl_table_exists($db)) { return null; }
        $conditions = [];
        $params = [];
        if ($candidateId > 0) { $conditions[] = 'candidate_id = ?'; $params[] = (string)$candidateId; }
        if ($sourceHash !== '') { $conditions[] = 'source_hash = ?'; $params[] = $sourceHash; }
        if ($payloadHash !== '') { $conditions[] = 'payload_hash = ?'; $params[] = $payloadHash; }
        if (!$conditions) { return null; }
        return gov_bridge_fetch_one(
            $db,
            'SELECT * FROM edxeix_pre_ride_candidate_closures WHERE (' . implode(' OR ', $conditions) . ') ORDER BY id DESC LIMIT 1',
            $params
        );
    }
}

if (!function_exists('gov_prcl_closure_state')) {
    /** @param array<string,mixed> $candidate @param array<string,mixed> $payload @return array<string,mixed> */
    function gov_prcl_closure_state(mysqli $db, array $candidate, array $payload): array
    {
        $candidateId = (int)($candidate['candidate_id'] ?? $candidate['id'] ?? 0);
        $sourceHash = trim((string)($candidate['source_hash'] ?? ''));
        $payloadHash = $payload ? gov_prcl_payload_hash($payload) : '';
        $row = gov_prcl_fetch_closure($db, $candidateId, $sourceHash, $payloadHash);
        $closed = is_array($row);
        return [
            'table_exists' => gov_prcl_table_exists($db),
            'closed' => $closed,
            'manual_submitted_v0' => $closed && (string)($row['closure_status'] ?? '') === 'manual_submitted_v0',
            'closure_id' => $closed ? (string)($row['id'] ?? '') : '',
            'closure_status' => $closed ? (string)($row['closure_status'] ?? '') : '',
            'method' => $closed ? (string)($row['method'] ?? '') : '',
            'submitted_by' => $closed ? (string)($row['submitted_by'] ?? '') : '',
            'submitted_at' => $closed ? (string)($row['submitted_at'] ?? '') : '',
            'note' => $closed ? (string)($row['note'] ?? '') : '',
            'candidate_id' => (string)$candidateId,
            'source_hash_16' => substr($sourceHash, 0, 16),
            'payload_hash' => $payloadHash,
            'payload_hash_16' => substr($payloadHash, 0, 16),
        ];
    }
}

if (!function_exists('gov_prcl_mark_manual')) {
    /** @param array<string,mixed> $input @return array<string,mixed> */
    function gov_prcl_mark_manual(mysqli $db, int $candidateId, array $input = []): array
    {
        if (!gov_prcl_table_exists($db)) {
            return [
                'ok' => false,
                'written' => false,
                'message' => 'Table edxeix_pre_ride_candidate_closures does not exist. Run the v3.2.31 additive SQL first.',
            ];
        }
        $row = gov_prcl_fetch_candidate_row($db, $candidateId);
        if (!is_array($row)) {
            return [
                'ok' => false,
                'written' => false,
                'message' => 'No captured pre-ride candidate found for candidate_id=' . $candidateId . '.',
            ];
        }

        $payload = gov_prcl_payload_from_candidate_row($row);
        $payloadHash = gov_prcl_payload_hash($payload);
        $sourceHash = (string)($row['source_hash'] ?? '');
        $method = trim((string)($input['method'] ?? 'v0_laptop_manual'));
        if ($method === '') { $method = 'v0_laptop_manual'; }
        $submittedBy = trim((string)($input['submitted_by'] ?? 'operator'));
        $submittedAtRaw = trim((string)($input['submitted_at'] ?? ''));
        $dateWarnings = [];
        if ($submittedAtRaw === '') {
            $submittedAt = gov_prcl_now();
            $dateWarnings[] = 'submitted_at_empty_defaulted_to_now';
        } else {
            $submittedAtTs = strtotime($submittedAtRaw);
            if ($submittedAtTs === false) {
                $submittedAt = gov_prcl_now();
                $dateWarnings[] = 'submitted_at_invalid_defaulted_to_now';
            } else {
                $submittedAt = date('Y-m-d H:i:s', $submittedAtTs);
            }
        }
        $note = trim((string)($input['note'] ?? 'Manually submitted via V0/laptop. Server-side retry blocked.'));

        $closure = [
            'candidate_id' => (string)$candidateId,
            'source_hash' => $sourceHash,
            'payload_hash' => $payloadHash,
            'closure_status' => 'manual_submitted_v0',
            'method' => $method,
            'submitted_by' => $submittedBy,
            'submitted_at' => $submittedAt,
            'note' => $note,
            'created_at' => gov_prcl_now(),
            'updated_at' => gov_prcl_now(),
        ];

        try {
            $existing = gov_prcl_fetch_closure($db, $candidateId, '', '');
            if (is_array($existing)) {
                $sql = "UPDATE edxeix_pre_ride_candidate_closures
                        SET source_hash = ?, payload_hash = ?, closure_status = ?, method = ?, submitted_by = ?, submitted_at = ?, note = ?, updated_at = NOW()
                        WHERE candidate_id = ?";
                $params = [$sourceHash, $payloadHash, 'manual_submitted_v0', $method, $submittedBy, $submittedAt, $note, (string)$candidateId];
                $stmt = $db->prepare($sql);
                if (function_exists('gov_bridge_bind_params')) {
                    gov_bridge_bind_params($stmt, str_repeat('s', count($params)), $params);
                } else {
                    $refs = [];
                    foreach ($params as $idx => $value) { $params[$idx] = (string)$value; $refs[$idx] = &$params[$idx]; }
                    $stmt->bind_param(str_repeat('s', count($params)), ...$refs);
                }
                $stmt->execute();
                $closureId = (int)($existing['id'] ?? 0);
            } else {
                $closureId = gov_bridge_insert_row($db, 'edxeix_pre_ride_candidate_closures', $closure);
            }

            // Archive the candidate so latest-ready cannot pick it again. Existing enum supports archived.
            $warnings = gov_prcl_json_decode($row['warnings_json'] ?? '[]');
            if (!is_array($warnings)) { $warnings = []; }
            $warnings[] = 'Candidate manually submitted via V0/laptop; server-side retry blocked by v3.2.31 closure.';
            $warnings = array_values(array_unique(array_map('strval', $warnings)));
            $warningsJson = function_exists('gov_prc_json') ? gov_prc_json($warnings) : json_encode($warnings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $sql = "UPDATE edxeix_pre_ride_candidates
                    SET status = 'archived', readiness_status = 'MANUALLY_SUBMITTED_VIA_V0', ready_for_edxeix = 0,
                        warnings_json = ?, updated_at = NOW()
                    WHERE id = ?";
            $params = [$warningsJson, (string)$candidateId];
            $stmt = $db->prepare($sql);
            if (function_exists('gov_bridge_bind_params')) {
                gov_bridge_bind_params($stmt, 'ss', $params);
            } else {
                $stmt->bind_param('ss', $params[0], $params[1]);
            }
            $stmt->execute();

            return [
                'ok' => true,
                'written' => true,
                'closure_id' => (string)$closureId,
                'candidate_id' => (string)$candidateId,
                'source_hash_16' => substr($sourceHash, 0, 16),
                'payload_hash' => $payloadHash,
                'payload_hash_16' => substr($payloadHash, 0, 16),
                'closure_status' => 'manual_submitted_v0',
                'method' => $method,
                'submitted_by' => $submittedBy,
                'submitted_at' => $submittedAt,
                'warnings' => $dateWarnings,
                'message' => 'Candidate marked manually submitted via V0/laptop and archived for server retry prevention.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'written' => false,
                'message' => 'Manual closure write failed: ' . $e->getMessage(),
            ];
        }
    }
}
