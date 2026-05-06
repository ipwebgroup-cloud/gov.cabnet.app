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

        if (!$startedAt) {
            $blockers[] = 'missing_started_at';
        } elseif ($startedAt <= $now->modify('+' . $guard . ' minutes')) {
            $blockers[] = 'started_at_not_future_guard_safe';
        }

        if (!$mapping['driver_ok']) {
            $blockers[] = 'driver_not_mapped';
        }
        if (!$mapping['vehicle_ok']) {
            $blockers[] = 'vehicle_not_mapped';
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
            'guarantee' => 'This evidence record does not create submission_jobs and does not POST to EDXEIX.',
        ];
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
