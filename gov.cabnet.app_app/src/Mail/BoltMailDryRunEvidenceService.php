<?php

namespace Bridge\Mail;

use DateTimeImmutable;
use DateTimeZone;
use mysqli;
use RuntimeException;

final class BoltMailDryRunEvidenceService
{
    public function __construct(
        private readonly mysqli $db,
        private readonly array $config
    ) {
    }

    public function listMailBookings(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "SELECT b.*, i.id AS intake_id, i.safety_status AS intake_safety_status,
                       i.parse_status AS intake_parse_status, i.linked_booking_id,
                       i.message_hash AS intake_message_hash, i.rejection_reason AS intake_rejection_reason
                FROM normalized_bookings b
                LEFT JOIN bolt_mail_intake i ON i.linked_booking_id = b.id
                WHERE b.source = 'bolt_mail'
                ORDER BY b.id DESC
                LIMIT " . $limit;
        return $this->fetchAll($sql);
    }

    public function listEvidence(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "SELECT e.*, b.customer_name, b.driver_name, b.vehicle_plate, b.started_at
                FROM bolt_mail_dry_run_evidence e
                LEFT JOIN normalized_bookings b ON b.id = e.normalized_booking_id
                ORDER BY e.id DESC
                LIMIT " . $limit;
        return $this->fetchAll($sql);
    }

    public function buildEvidencePreview(int $bookingId): array
    {
        $booking = $this->findBooking($bookingId);
        if (!$booking) {
            throw new RuntimeException('Booking was not found.');
        }
        if ((string)($booking['source'] ?? '') !== 'bolt_mail') {
            throw new RuntimeException('Only source=bolt_mail bookings can be used here.');
        }

        $intake = $this->findIntakeByBookingId($bookingId);
        $mapping = $this->resolveMappings($booking);
        $payload = $this->buildEdxeixPayload($booking, $mapping);
        $safety = $this->buildSafetySnapshot($booking, $intake, $mapping);

        return [
            'booking' => $booking,
            'intake' => $intake,
            'mapping' => $mapping,
            'payload' => $payload,
            'safety' => $safety,
            'payload_hash' => hash('sha256', $this->json($payload)),
            'can_record' => empty($safety['blockers']),
        ];
    }

    public function recordEvidence(int $bookingId, string $createdBy = 'ops'): int
    {
        $preview = $this->buildEvidencePreview($bookingId);
        if (!$preview['can_record']) {
            throw new RuntimeException('Dry-run evidence cannot be recorded while blockers exist: ' . implode(', ', $preview['safety']['blockers']));
        }

        $sql = "INSERT INTO bolt_mail_dry_run_evidence
                (normalized_booking_id, intake_id, source, evidence_status, payload_hash,
                 request_payload_json, mapping_snapshot_json, safety_snapshot_json, notes, created_by, created_at)
                VALUES (?, ?, 'bolt_mail', 'recorded', ?, ?, ?, ?, ?, ?, NOW())";

        $notes = 'Dry-run evidence only. No submission job was created. No EDXEIX POST was performed.';
        $normalizedBookingId = $bookingId;
        $intakeId = isset($preview['intake']['id']) ? (int)$preview['intake']['id'] : null;
        $payloadHash = $preview['payload_hash'];
        $requestPayloadJson = $this->json($preview['payload']);
        $mappingSnapshotJson = $this->json($preview['mapping']);
        $safetySnapshotJson = $this->json($preview['safety']);
        $createdByValue = $createdBy;

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            'iissssss',
            $normalizedBookingId,
            $intakeId,
            $payloadHash,
            $requestPayloadJson,
            $mappingSnapshotJson,
            $safetySnapshotJson,
            $notes,
            $createdByValue
        );
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    public function countSubmissionJobs(): array
    {
        $jobs = $this->fetchOne("SELECT COUNT(*) AS total, SUM(CASE WHEN status IN ('pending','processing') THEN 1 ELSE 0 END) AS open_count FROM submission_jobs") ?: [];
        $attempts = $this->fetchOne("SELECT COUNT(*) AS total FROM submission_attempts") ?: [];
        return [
            'submission_jobs_total' => (int)($jobs['total'] ?? 0),
            'open_submission_jobs' => (int)($jobs['open_count'] ?? 0),
            'submission_attempts_total' => (int)($attempts['total'] ?? 0),
        ];
    }
    public function findEvidenceById(int $evidenceId): ?array
    {
        return $this->fetchOne(
            "SELECT e.*, b.customer_name, b.driver_name, b.vehicle_plate, b.started_at, b.ended_at, b.boarding_point, b.disembark_point,
                    i.parse_status AS intake_parse_status, i.safety_status AS intake_safety_status, i.parsed_pickup_at AS intake_pickup_at
             FROM bolt_mail_dry_run_evidence e
             LEFT JOIN normalized_bookings b ON b.id = e.normalized_booking_id
             LEFT JOIN bolt_mail_intake i ON i.id = e.intake_id
             WHERE e.id = ?
             LIMIT 1",
            [$evidenceId],
            'i'
        );
    }

    public function syntheticCleanupPreview(): array
    {
        $evidenceRows = $this->syntheticEvidenceRows();
        $bookingRows = $this->syntheticBookingRows();
        $bookingIds = [];
        foreach ($bookingRows as $row) {
            $bookingIds[] = (int)($row['id'] ?? 0);
        }
        foreach ($evidenceRows as $row) {
            $bookingIds[] = (int)($row['normalized_booking_id'] ?? 0);
        }
        $bookingIds = array_values(array_unique(array_filter($bookingIds)));

        $linkedIntakeRows = [];
        if ($bookingIds) {
            $sql = "SELECT id, linked_booking_id, safety_status, customer_name, parsed_pickup_at
                    FROM bolt_mail_intake
                    WHERE linked_booking_id IN (" . $this->placeholders(count($bookingIds)) . ")
                      AND customer_name LIKE 'CABNET TEST%'";
            $linkedIntakeRows = $this->fetchAll($sql, $bookingIds, str_repeat('i', count($bookingIds)));
        }

        return [
            'synthetic_evidence_rows' => count($evidenceRows),
            'synthetic_booking_rows' => count($bookingRows),
            'linked_synthetic_intake_rows' => count($linkedIntakeRows),
            'evidence_rows' => $evidenceRows,
            'booking_rows' => $bookingRows,
            'linked_intake_rows' => $linkedIntakeRows,
            'booking_ids' => $bookingIds,
        ];
    }

    public function cleanupSyntheticEvidence(string $confirmedText): array
    {
        if ($confirmedText !== 'DELETE_SYNTHETIC_ONLY') {
            throw new RuntimeException('Cleanup confirmation text did not match DELETE_SYNTHETIC_ONLY.');
        }

        $preview = $this->syntheticCleanupPreview();
        $bookingIds = array_values(array_unique(array_map('intval', $preview['booking_ids'])));
        $evidenceIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $preview['evidence_rows']
        ))));
        $bookingIdList = $bookingIds ? implode(',', $bookingIds) : '0';
        $evidenceIdList = $evidenceIds ? implode(',', array_map('intval', $evidenceIds)) : '0';

        $result = [
            'evidence_deleted' => 0,
            'bookings_deleted' => 0,
            'intake_rows_unlinked' => 0,
            'booking_ids' => $bookingIds,
            'evidence_ids' => $evidenceIds,
            'safety' => 'Synthetic cleanup only: CABNET TEST / synthetic evidence rows. No submission jobs. No EDXEIX POST.',
        ];

        $this->db->begin_transaction();
        try {
            if ($bookingIds) {
                $sql = "UPDATE bolt_mail_intake
                        SET linked_booking_id = NULL,
                            safety_status = 'blocked_past',
                            rejection_reason = TRIM(CONCAT(COALESCE(NULLIF(rejection_reason, ''), ''), CHAR(10), 'Synthetic dry-run evidence cleanup: local synthetic booking removed.')),
                            updated_at = NOW()
                        WHERE linked_booking_id IN (" . $bookingIdList . ")
                          AND customer_name LIKE 'CABNET TEST%'";
                $this->db->query($sql);
                $result['intake_rows_unlinked'] = $this->db->affected_rows;
            }

            if ($evidenceIds) {
                $sql = "DELETE e FROM bolt_mail_dry_run_evidence e
                        LEFT JOIN normalized_bookings b ON b.id = e.normalized_booking_id
                        WHERE e.id IN (" . $evidenceIdList . ")
                          AND (
                            b.customer_name LIKE 'CABNET TEST%'
                            OR b.notes LIKE '%Synthetic%'
                            OR e.safety_snapshot_json LIKE '%\"synthetic\": true%'
                            OR e.safety_snapshot_json LIKE '%\"synthetic\":true%'
                          )";
                $this->db->query($sql);
                $result['evidence_deleted'] = $this->db->affected_rows;
            }

            if ($bookingIds) {
                $sql = "DELETE FROM normalized_bookings
                        WHERE id IN (" . $bookingIdList . ")
                          AND source = 'bolt_mail'
                          AND (customer_name LIKE 'CABNET TEST%' OR notes LIKE '%Synthetic%')";
                $this->db->query($sql);
                $result['bookings_deleted'] = $this->db->affected_rows;
            }

            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }


    private function syntheticEvidenceRows(): array
    {
        return $this->fetchAll(
            "SELECT e.*, b.customer_name, b.driver_name, b.vehicle_plate, b.started_at, b.notes
             FROM bolt_mail_dry_run_evidence e
             LEFT JOIN normalized_bookings b ON b.id = e.normalized_booking_id
             WHERE b.customer_name LIKE 'CABNET TEST%'
                OR b.notes LIKE '%Synthetic%'
                OR e.safety_snapshot_json LIKE '%\"synthetic\": true%'
                OR e.safety_snapshot_json LIKE '%\"synthetic\":true%'
             ORDER BY e.id DESC
             LIMIT 200"
        );
    }

    private function syntheticBookingRows(): array
    {
        return $this->fetchAll(
            "SELECT id, customer_name, driver_name, vehicle_plate, started_at, created_at, notes
             FROM normalized_bookings
             WHERE source = 'bolt_mail'
               AND (customer_name LIKE 'CABNET TEST%' OR notes LIKE '%Synthetic%')
             ORDER BY id DESC
             LIMIT 200"
        );
    }

    private function placeholders(int $count): string
    {
        return implode(',', array_fill(0, max(1, $count), '?'));
    }

    private function findBooking(int $bookingId): ?array
    {
        return $this->fetchOne('SELECT * FROM normalized_bookings WHERE id = ? LIMIT 1', [$bookingId], 'i');
    }

    private function findIntakeByBookingId(int $bookingId): ?array
    {
        return $this->fetchOne('SELECT * FROM bolt_mail_intake WHERE linked_booking_id = ? LIMIT 1', [$bookingId], 'i');
    }

    private function resolveMappings(array $booking): array
    {
        $driver = null;
        $vehicle = null;
        $startingPoint = null;

        $driverExternalId = trim((string)($booking['driver_external_id'] ?? ''));
        $driverName = trim((string)($booking['driver_name'] ?? ''));
        if ($driverExternalId !== '') {
            $driver = $this->fetchOne('SELECT * FROM mapping_drivers WHERE external_driver_id = ? AND is_active = 1 LIMIT 1', [$driverExternalId], 's');
        }
        if (!$driver && $driverName !== '') {
            $driver = $this->fetchOne('SELECT * FROM mapping_drivers WHERE external_driver_name = ? AND is_active = 1 LIMIT 1', [$driverName], 's');
        }

        $vehicleExternalId = trim((string)($booking['vehicle_external_id'] ?? ''));
        $plate = trim((string)($booking['vehicle_plate'] ?? ''));
        if ($vehicleExternalId !== '') {
            $vehicle = $this->fetchOne('SELECT * FROM mapping_vehicles WHERE external_vehicle_id = ? AND is_active = 1 LIMIT 1', [$vehicleExternalId], 's');
        }
        if (!$vehicle && $plate !== '') {
            $vehicle = $this->fetchOne('SELECT * FROM mapping_vehicles WHERE plate = ? AND is_active = 1 LIMIT 1', [$plate], 's');
        }

        $startingPointKey = trim((string)($booking['starting_point_key'] ?? 'edra_mas'));
        if ($startingPointKey === '') {
            $startingPointKey = 'edra_mas';
        }
        $startingPoint = $this->fetchOne('SELECT * FROM mapping_starting_points WHERE internal_key = ? AND is_active = 1 LIMIT 1', [$startingPointKey], 's');

        return [
            'driver_ok' => $driver !== null && !empty($driver['edxeix_driver_id']),
            'vehicle_ok' => $vehicle !== null && !empty($vehicle['edxeix_vehicle_id']),
            'starting_point_ok' => $startingPoint !== null && !empty($startingPoint['edxeix_starting_point_id']),
            'driver' => $driver,
            'vehicle' => $vehicle,
            'starting_point' => $startingPoint,
        ];
    }

    private function buildSafetySnapshot(array $booking, ?array $intake, array $mapping): array
    {
        $blockers = [];
        $warnings = [];
        $now = new DateTimeImmutable('now', $this->tz());
        $guard = (int)($this->config['edxeix']['future_start_guard_minutes'] ?? 2);
        $startedAtRaw = (string)($booking['started_at'] ?? '');
        $startedAt = $this->parseLocalDateTime($startedAtRaw);

        $lateAadeReceiptRecovery = $this->isLateAadeReceiptRecovery($booking, $intake, $startedAt, $now);

        if (!$startedAt) {
            $blockers[] = 'missing_started_at';
        } elseif ($startedAt <= $now->modify('+' . $guard . ' minutes')) {
            if ($lateAadeReceiptRecovery) {
                $warnings[] = 'started_at_not_future_guard_safe_but_allowed_for_aade_receipt_recovery';
                $warnings[] = 'not_edxeix_live_safe_if_pickup_is_past';
            } else {
                $blockers[] = 'started_at_not_future_guard_safe';
            }
        }

        if (!$mapping['driver_ok']) {
            $blockers[] = 'driver_not_mapped';
        }
        if (!$mapping['vehicle_ok']) {
            if (!empty($lateAadeReceiptRecovery)) {
                $warnings[] = 'vehicle_not_mapped_but_allowed_for_aade_receipt_recovery';
                $warnings[] = 'not_edxeix_live_safe_until_vehicle_is_mapped';
            } else {
                $blockers[] = 'vehicle_not_mapped';
            }
        }
        if (!$mapping['starting_point_ok']) {
            $blockers[] = 'starting_point_not_mapped';
        }

        if ((string)($booking['source'] ?? '') !== 'bolt_mail') {
            $blockers[] = 'not_bolt_mail_source';
        }

        $status = strtolower(trim((string)($booking['status'] ?? $booking['order_status'] ?? '')));
        if (in_array($status, ['finished','completed','cancelled','canceled','client_cancelled','driver_cancelled','driver_cancelled_after_accept','expired','rejected','failed'], true)) {
            $blockers[] = 'terminal_status';
        }

        $customerName = (string)($booking['customer_name'] ?? '');
        $synthetic = stripos($customerName, 'CABNET TEST') !== false || stripos((string)($booking['notes'] ?? ''), 'Synthetic') !== false;
        if ($synthetic) {
            $warnings[] = 'synthetic_test_booking';
        }

        return [
            'ok' => empty($blockers),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'dry_run_only' => true,
            'live_submit_enabled' => !empty($this->config['edxeix']['live_submit_enabled']),
            'app_dry_run' => !empty($this->config['app']['dry_run']),
            'future_guard_minutes' => $guard,
            'now' => $now->format('Y-m-d H:i:s'),
            'started_at' => $startedAtRaw,
            'intake_id' => $intake['id'] ?? null,
            'intake_safety_status' => $intake['safety_status'] ?? null,
            'synthetic' => $synthetic,
            'late_aade_receipt_recovery' => $lateAadeReceiptRecovery,
            'guarantee' => 'This evidence record does not create submission_jobs and does not POST to EDXEIX.',
        ];
    }

    private function isLateAadeReceiptRecovery(array $booking, ?array $intake, ?DateTimeImmutable $startedAt, DateTimeImmutable $now): bool
    {
        if (empty($this->config['receipts']['aade_mydata']['allow_late_bolt_mail_receipt'])) {
            return false;
        }

        if ((string)($booking['source'] ?? '') !== 'bolt_mail') {
            return false;
        }

        if (!$startedAt || $startedAt > $now) {
            return false;
        }

        if (!is_array($intake)) {
            return false;
        }

        if ((string)($intake['parse_status'] ?? '') !== 'parsed') {
            return false;
        }

        if (!in_array((string)($intake['safety_status'] ?? ''), ['blocked_past', 'blocked_too_soon', 'future_candidate'], true)) {
            return false;
        }

        $customer = strtoupper((string)($booking['customer_name'] ?? $intake['customer_name'] ?? ''));
        if (str_contains($customer, 'CABNET TEST') || str_contains($customer, 'DO NOT SUBMIT')) {
            return false;
        }

        return true;
    }

    private function buildEdxeixPayload(array $booking, array $mapping): array
    {
        return [
            '_token' => '[loaded from saved EDXEIX session only at submit time]',
            'broker' => (string)($this->config['edxeix']['default_broker'] ?? 'Bolt') ?: 'Bolt',
            'lessor' => (string)($this->config['edxeix']['lessor_id'] ?? ''),
            'lessee[type]' => ((string)($booking['customer_type'] ?? 'natural')) === 'legal' ? 'legal' : 'natural',
            'lessee[name]' => (string)($booking['customer_name'] ?? ''),
            'lessee[vat_number]' => (string)($booking['customer_vat_number'] ?? ''),
            'lessee[legal_representative]' => (string)($booking['customer_representative'] ?? ''),
            'driver' => (string)($mapping['driver']['edxeix_driver_id'] ?? ''),
            'vehicle' => (string)($mapping['vehicle']['edxeix_vehicle_id'] ?? ''),
            'starting_point_id' => (string)($mapping['starting_point']['edxeix_starting_point_id'] ?? ''),
            'boarding_point' => (string)($booking['boarding_point'] ?? $booking['pickup_address'] ?? ''),
            'coordinates' => (string)($booking['coordinates'] ?? ''),
            'disembark_point' => (string)($booking['disembark_point'] ?? $booking['destination_address'] ?? ''),
            'drafted_at' => $this->formatForEdxeix((string)($booking['drafted_at'] ?? date('Y-m-d H:i:s'))),
            'started_at' => $this->formatForEdxeix((string)($booking['started_at'] ?? '')),
            'ended_at' => $this->formatForEdxeix((string)($booking['ended_at'] ?? '')),
            'price' => number_format((float)($booking['price'] ?? 0), 2, '.', ''),
        ];
    }

    private function parseLocalDateTime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value, $this->tz());
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatForEdxeix(string $value): string
    {
        $dt = $this->parseLocalDateTime($value);
        return $dt ? $dt->format('d/m/Y H:i') : $value;
    }

    private function tz(): DateTimeZone
    {
        return new DateTimeZone((string)($this->config['app']['timezone'] ?? 'Europe/Athens'));
    }

    private function fetchOne(string $sql, array $params = [], string $types = ''): ?array
    {
        $rows = $this->fetchAll($sql, $params, $types);
        return $rows[0] ?? null;
    }

    private function fetchAll(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->db->prepare($sql);
        if ($params) {
            if ($types === '') {
                $types = str_repeat('s', count($params));
            }
            $refs = [$types];
            foreach ($params as $key => $value) {
                $refs[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
