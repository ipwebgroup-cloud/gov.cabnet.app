<?php

namespace Bridge\Mail;

use Bridge\Config;
use Bridge\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class BoltMailIntakeBookingBridge
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config
    ) {
    }

    public function listRecentIntakeRows(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->db->fetchAll(
            'SELECT * FROM bolt_mail_intake ORDER BY id DESC LIMIT ' . $limit
        );
    }

    public function listFutureCandidates(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        /*
         * v6.1.1:
         * For EDXEIX, only true future candidates may live-submit.
         * For AADE/myDATA receipts, however, a real parsed Bolt mail that arrives
         * at/after pickup still needs a normalized booking so the official receipt
         * can be issued. Limit late recovery to rows created after the configured
         * AADE auto-issue window, and never include synthetic/test customer rows.
         */
        $notBefore = trim((string)$this->config->get('receipts.aade_mydata.auto_issue_not_before', '1970-01-01 00:00:00'));
        $lateRecoveryEnabled = filter_var(
            $this->config->get('receipts.aade_mydata.allow_late_bolt_mail_receipt', true),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($lateRecoveryEnabled) {
            return $this->db->fetchAll(
                "SELECT * FROM bolt_mail_intake
                 WHERE parse_status = 'parsed'
                   AND linked_booking_id IS NULL
                   AND (
                        safety_status = 'future_candidate'
                        OR (
                            safety_status IN ('blocked_past','blocked_too_soon')
                            AND created_at >= ?
                            AND UPPER(COALESCE(customer_name,'')) NOT LIKE '%CABNET TEST%'
                            AND UPPER(COALESCE(customer_name,'')) NOT LIKE '%DO NOT SUBMIT%'
                        )
                   )
                 ORDER BY parsed_pickup_at ASC, id ASC
                 LIMIT " . $limit,
                [$notBefore]
            );
        }

        return $this->db->fetchAll(
            "SELECT * FROM bolt_mail_intake
             WHERE parse_status = 'parsed'
               AND safety_status = 'future_candidate'
               AND linked_booking_id IS NULL
             ORDER BY parsed_pickup_at ASC, id ASC
             LIMIT " . $limit
        );
    }

    public function previewById(int $intakeId): array
    {
        $row = $this->findIntakeRow($intakeId);
        if (!$row) {
            return [
                'ok' => false,
                'create_allowed' => false,
                'intake_id' => $intakeId,
                'errors' => ['intake_row_not_found'],
                'message' => 'Mail intake row was not found.',
            ];
        }

        return $this->previewRow($row);
    }

    public function previewRow(array $row): array
    {
        $errors = [];
        $warnings = [];

        $intakeId = (int)($row['id'] ?? 0);
        $parseStatus = (string)($row['parse_status'] ?? '');
        $safetyStatus = (string)($row['safety_status'] ?? '');

        if ($parseStatus !== 'parsed') {
            $errors[] = 'intake_not_parsed';
        }

        $lateReceiptRecovery = $this->isLateReceiptRecoveryRow($row);
        if ($safetyStatus !== 'future_candidate' && !$lateReceiptRecovery) {
            $errors[] = 'intake_not_future_candidate';
        }
        if ($lateReceiptRecovery) {
            $warnings[] = 'late_bolt_mail_receipt_recovery';
            $warnings[] = 'not_edxeix_live_safe_if_pickup_is_past';
        }

        foreach ([
            'customer_name' => 'missing_customer_name',
            'driver_name' => 'missing_driver_name',
            'vehicle_plate' => 'missing_vehicle_plate',
            'pickup_address' => 'missing_pickup_address',
            'dropoff_address' => 'missing_dropoff_address',
            'parsed_pickup_at' => 'missing_pickup_time',
        ] as $column => $error) {
            if (trim((string)($row[$column] ?? '')) === '') {
                $errors[] = $error;
            }
        }

        $timeCheck = $this->checkFutureGuard((string)($row['parsed_pickup_at'] ?? ''));
        if (!$timeCheck['ok'] && !$lateReceiptRecovery) {
            $errors[] = $timeCheck['code'];
        }
        if (!$timeCheck['ok'] && $lateReceiptRecovery) {
            $warnings[] = 'future_guard_bypassed_for_aade_receipt_only:' . $timeCheck['code'];
        }

        $normalized = $this->buildNormalizedCandidate($row);
        $mapping = $this->resolveMapping($normalized);
        if (!$mapping['driver']['ok']) {
            $errors[] = 'driver_not_mapped';
        }
        if (!$mapping['vehicle']['ok']) {
            if (!empty($lateReceiptRecovery)) {
                $warnings[] = 'vehicle_not_mapped_but_allowed_for_aade_receipt_recovery';
                $warnings[] = 'not_edxeix_live_safe_until_vehicle_is_mapped';
            } else {
                $errors[] = 'vehicle_not_mapped';
            }
        }
        if (!$mapping['starting_point']['ok']) {
            $errors[] = 'starting_point_not_mapped';
        }

        $existingBooking = null;
        if (($normalized['dedupe_hash'] ?? '') !== '') {
            $existingBooking = $this->findBookingByDedupeHash((string)$normalized['dedupe_hash']);
            if ($existingBooking) {
                $warnings[] = 'normalized_booking_already_exists';
            }
        }

        $edxeixPreview = $this->buildEdxeixPreviewPayload($normalized, $mapping);
        $createAllowed = empty($errors);

        return [
            'ok' => empty($errors),
            'create_allowed' => $createAllowed,
            'intake_id' => $intakeId,
            'linked_booking_id' => $row['linked_booking_id'] ?? null,
            'existing_booking_id' => $existingBooking['id'] ?? null,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'time_check' => $timeCheck,
            'mapping' => $mapping,
            'normalized_candidate' => $normalized,
            'edxeix_preview_payload' => $edxeixPreview,
            'message' => $createAllowed
                ? 'Future candidate is ready to create a local normalized booking for preflight review.'
                : 'Future candidate is blocked until the listed errors are resolved.',
        ];
    }

    public function createLocalPreflightBooking(int $intakeId): array
    {
        $this->db->beginTransaction();

        try {
            $row = $this->findIntakeRow($intakeId, true);
            if (!$row) {
                throw new RuntimeException('Mail intake row was not found.');
            }

            if (!empty($row['linked_booking_id'])) {
                $existing = $this->findBooking((int)$row['linked_booking_id']);
                if ($existing) {
                    $this->db->commit();
                    return [
                        'ok' => true,
                        'created' => false,
                        'linked' => true,
                        'booking_id' => (int)$existing['id'],
                        'message' => 'Mail intake row is already linked to a normalized booking.',
                    ];
                }
            }

            $preview = $this->previewRow($row);
            if (!$preview['create_allowed']) {
                throw new RuntimeException('Blocked: ' . implode(', ', $preview['errors']));
            }

            $normalized = $preview['normalized_candidate'];
            $existing = $this->findBookingByDedupeHash((string)$normalized['dedupe_hash']);
            if ($existing) {
                $this->linkIntakeToBooking($intakeId, (int)$existing['id']);
                $this->db->commit();

                return [
                    'ok' => true,
                    'created' => false,
                    'linked' => true,
                    'booking_id' => (int)$existing['id'],
                    'message' => 'Duplicate normalized booking already existed; intake row was linked to it.',
                ];
            }

            $bookingId = $this->insertNormalizedBooking($normalized);
            $this->linkIntakeToBooking($intakeId, $bookingId);
            $this->db->commit();

            return [
                'ok' => true,
                'created' => true,
                'linked' => true,
                'booking_id' => $bookingId,
                'message' => 'Local normalized booking was created for EDXEIX preflight review only. No submission job was created.',
            ];
        } catch (Throwable $e) {
            $this->db->rollback();
            return [
                'ok' => false,
                'created' => false,
                'linked' => false,
                'booking_id' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function findIntakeRow(int $intakeId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM bolt_mail_intake WHERE id = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->db->fetchOne($sql, [$intakeId], 'i');
    }

    private function findBooking(int $bookingId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM normalized_bookings WHERE id = ? LIMIT 1', [$bookingId], 'i');
    }

    private function findBookingByDedupeHash(string $dedupeHash): ?array
    {
        return $this->db->fetchOne('SELECT * FROM normalized_bookings WHERE dedupe_hash = ? LIMIT 1', [$dedupeHash]);
    }

    private function buildNormalizedCandidate(array $row): array
    {
        $intakeId = (int)($row['id'] ?? 0);
        $pickupAt = $this->mysqlDate((string)($row['parsed_pickup_at'] ?? ''));
        $endAt = $this->mysqlDate((string)($row['parsed_end_at'] ?? ''));
        if ($endAt === null && $pickupAt !== null) {
            $endAt = date('Y-m-d H:i:s', strtotime($pickupAt . ' +30 minutes'));
        }

        $draftedAt = $this->mysqlDate((string)($row['parsed_start_at'] ?? ''))
            ?: (string)($row['received_at'] ?? '')
            ?: date('Y-m-d H:i:s');

        $pickup = trim((string)($row['pickup_address'] ?? ''));
        $dropoff = trim((string)($row['dropoff_address'] ?? ''));
        $customer = trim((string)($row['customer_name'] ?? ''));
        $driver = trim((string)($row['driver_name'] ?? ''));
        $plate = strtoupper(trim((string)($row['vehicle_plate'] ?? '')));
        $mobile = trim((string)($row['customer_mobile'] ?? ''));
        $price = $this->parsePrice((string)($row['estimated_price_raw'] ?? ''));
        $signatureHash = $this->tripSignatureHash($row);
        $sourceTripId = 'mail:' . substr($signatureHash, 0, 32);
        $sourceBookingId = 'mail-intake-' . $intakeId;
        $defaultStartingPointKey = (string)$this->config->get('edxeix.default_starting_point_key', 'edra_mas');

        $notes = implode("\n", array_filter([
            'Bolt pre-ride email intake row #' . $intakeId,
            'Source mailbox: ' . (string)($row['source_mailbox'] ?? 'bolt-bridge@gov.cabnet.app'),
            'Customer mobile: ' . $mobile,
            'Operator: ' . (string)($row['operator_raw'] ?? ''),
            'Safety: local preflight candidate only; no EDXEIX live submit.',
            $this->isLateReceiptRecoveryRow($row) ? 'v6.1.1: Late Bolt mail AADE receipt recovery booking. EDXEIX live gate still blocks past trips.' : '',
        ]));

        $normalized = [
            'source' => 'bolt_mail',
            'source_system' => 'bolt_mail',
            'source_trip_id' => $sourceTripId,
            'source_booking_id' => $sourceBookingId,
            'external_order_id' => $sourceTripId,
            'order_reference' => $sourceTripId,
            'source_trip_reference' => $sourceTripId,
            'status' => 'confirmed',
            'order_status' => 'confirmed',
            'customer_type' => 'natural',
            'customer_name' => $customer,
            'passenger_name' => $customer,
            'lessee_name' => $customer,
            'driver_external_id' => null,
            'driver_name' => $driver,
            'vehicle_external_id' => null,
            'vehicle_plate' => $plate,
            'starting_point_key' => $defaultStartingPointKey,
            'boarding_point' => $pickup,
            'pickup_address' => $pickup,
            'coordinates' => null,
            'disembark_point' => $dropoff,
            'destination_address' => $dropoff,
            'drafted_at' => $draftedAt,
            'started_at' => $pickupAt ?: '',
            'ended_at' => $endAt,
            'price' => $price,
            'currency' => 'EUR',
            'broker_key' => (string)$this->config->get('edxeix.default_broker', 'Bolt'),
            'notes' => $notes,
            'is_scheduled' => 1,
            'edxeix_ready' => 0,
            'normalized_payload_json' => '',
            'raw_payload_json' => json_encode($this->publicIntakeSnapshot($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'edxeix_payload_json' => null,
            'dedupe_hash' => hash('sha256', 'normalized_bookings|bolt_mail|' . $signatureHash),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $normalized['normalized_payload_json'] = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $normalized;
    }

    private function resolveMapping(array $normalized): array
    {
        $driverName = trim((string)($normalized['driver_name'] ?? ''));
        $plate = strtoupper(trim((string)($normalized['vehicle_plate'] ?? '')));
        $startingPointKey = trim((string)($normalized['starting_point_key'] ?? 'edra_mas'));

        $driver = null;
        if ($driverName !== '') {
            $driver = $this->db->fetchOne(
                'SELECT * FROM mapping_drivers WHERE external_driver_name = ? AND is_active = 1 LIMIT 1',
                [$driverName]
            );
        }

        $vehicle = null;
        if ($plate !== '') {
            $vehicle = $this->db->fetchOne(
                'SELECT * FROM mapping_vehicles WHERE UPPER(plate) = ? AND is_active = 1 LIMIT 1',
                [$plate]
            );
        }

        $startingPoint = null;
        if ($startingPointKey !== '') {
            $startingPoint = $this->db->fetchOne(
                'SELECT * FROM mapping_starting_points WHERE internal_key = ? AND is_active = 1 LIMIT 1',
                [$startingPointKey]
            );
        }

        $configStartingPointId = (string)$this->config->get('edxeix.default_starting_point_id', '');
        $startingPointFromConfig = false;
        if (!$startingPoint && $configStartingPointId !== '' && $configStartingPointId !== '0') {
            $startingPointFromConfig = true;
            $startingPoint = [
                'internal_key' => $startingPointKey ?: 'config_default',
                'label' => 'Config default starting point',
                'edxeix_starting_point_id' => $configStartingPointId,
                'source' => 'config',
            ];
        }

        return [
            'driver' => [
                'ok' => $driver !== null && trim((string)($driver['edxeix_driver_id'] ?? '')) !== '',
                'row' => $driver,
                'lookup' => $driverName,
                'edxeix_driver_id' => $driver['edxeix_driver_id'] ?? null,
            ],
            'vehicle' => [
                'ok' => $vehicle !== null && trim((string)($vehicle['edxeix_vehicle_id'] ?? '')) !== '',
                'row' => $vehicle,
                'lookup' => $plate,
                'edxeix_vehicle_id' => $vehicle['edxeix_vehicle_id'] ?? null,
            ],
            'starting_point' => [
                'ok' => $startingPoint !== null && trim((string)($startingPoint['edxeix_starting_point_id'] ?? '')) !== '',
                'row' => $startingPoint,
                'lookup' => $startingPointKey,
                'edxeix_starting_point_id' => $startingPoint['edxeix_starting_point_id'] ?? null,
                'from_config_fallback' => $startingPointFromConfig,
            ],
        ];
    }

    private function buildEdxeixPreviewPayload(array $normalized, array $mapping): array
    {
        return [
            'broker' => (string)($normalized['broker_key'] ?? $this->config->get('edxeix.default_broker', 'Bolt')),
            'lessor' => (string)$this->config->get('edxeix.lessor_id', ''),
            'lessee[type]' => 'natural',
            'lessee[name]' => (string)($normalized['customer_name'] ?? ''),
            'lessee[vat_number]' => '',
            'lessee[legal_representative]' => '',
            'driver' => (string)($mapping['driver']['edxeix_driver_id'] ?? ''),
            'vehicle' => (string)($mapping['vehicle']['edxeix_vehicle_id'] ?? ''),
            'starting_point_id' => (string)($mapping['starting_point']['edxeix_starting_point_id'] ?? ''),
            'boarding_point' => (string)($normalized['boarding_point'] ?? ''),
            'coordinates' => (string)($normalized['coordinates'] ?? ''),
            'disembark_point' => (string)($normalized['disembark_point'] ?? ''),
            'drafted_at' => $this->formatEdxeixDate((string)($normalized['drafted_at'] ?? '')),
            'started_at' => $this->formatEdxeixDate((string)($normalized['started_at'] ?? '')),
            'ended_at' => $this->formatEdxeixDate((string)($normalized['ended_at'] ?? '')),
            'price' => number_format((float)($normalized['price'] ?? 0), 2, '.', ''),
        ];
    }

    private function isLateReceiptRecoveryRow(array $row): bool
    {
        if (!filter_var($this->config->get('receipts.aade_mydata.allow_late_bolt_mail_receipt', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if ((string)($row['parse_status'] ?? '') !== 'parsed') {
            return false;
        }

        if (!in_array((string)($row['safety_status'] ?? ''), ['blocked_past', 'blocked_too_soon'], true)) {
            return false;
        }

        if (!empty($row['linked_booking_id'])) {
            return false;
        }

        $customer = strtoupper((string)($row['customer_name'] ?? ''));
        if (str_contains($customer, 'CABNET TEST') || str_contains($customer, 'DO NOT SUBMIT')) {
            return false;
        }

        $notBeforeRaw = trim((string)$this->config->get('receipts.aade_mydata.auto_issue_not_before', ''));
        if ($notBeforeRaw === '') {
            return false;
        }

        try {
            $timezone = new DateTimeZone((string)$this->config->get('app.timezone', 'Europe/Athens'));
            $createdAt = new DateTimeImmutable((string)($row['created_at'] ?? ''), $timezone);
            $notBefore = new DateTimeImmutable($notBeforeRaw, $timezone);
            return $createdAt >= $notBefore;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkFutureGuard(string $pickupAt): array
    {
        $pickupAt = trim($pickupAt);
        $guardMinutes = (int)$this->config->get('edxeix.future_start_guard_minutes', 30);
        $timezone = new DateTimeZone((string)$this->config->get('app.timezone', 'Europe/Athens'));
        $now = new DateTimeImmutable('now', $timezone);

        if ($pickupAt === '') {
            return [
                'ok' => false,
                'code' => 'missing_pickup_time',
                'guard_minutes' => $guardMinutes,
                'now' => $now->format('Y-m-d H:i:s'),
            ];
        }

        try {
            $pickup = new DateTimeImmutable($pickupAt, $timezone);
        } catch (Throwable) {
            return [
                'ok' => false,
                'code' => 'invalid_pickup_time',
                'guard_minutes' => $guardMinutes,
                'now' => $now->format('Y-m-d H:i:s'),
            ];
        }

        if ($pickup <= $now) {
            return [
                'ok' => false,
                'code' => 'pickup_already_past',
                'guard_minutes' => $guardMinutes,
                'now' => $now->format('Y-m-d H:i:s'),
                'pickup_at' => $pickup->format('Y-m-d H:i:s'),
            ];
        }

        $guardAt = $now->modify('+' . $guardMinutes . ' minutes');
        if ($pickup < $guardAt) {
            return [
                'ok' => false,
                'code' => 'pickup_too_soon_for_guard',
                'guard_minutes' => $guardMinutes,
                'now' => $now->format('Y-m-d H:i:s'),
                'guard_at' => $guardAt->format('Y-m-d H:i:s'),
                'pickup_at' => $pickup->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'ok' => true,
            'code' => 'future_guard_passed',
            'guard_minutes' => $guardMinutes,
            'now' => $now->format('Y-m-d H:i:s'),
            'guard_at' => $guardAt->format('Y-m-d H:i:s'),
            'pickup_at' => $pickup->format('Y-m-d H:i:s'),
        ];
    }

    private function insertNormalizedBooking(array $normalized): int
    {
        $row = $this->filterForTable('normalized_bookings', $normalized);
        if (!$row) {
            throw new RuntimeException('No compatible normalized_bookings columns are available.');
        }

        $columns = array_keys($row);
        $quotedColumns = '`' . implode('`,`', array_map([$this, 'safeIdentifier'], $columns)) . '`';
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO normalized_bookings (' . $quotedColumns . ') VALUES (' . $placeholders . ')';

        return $this->db->insert($sql, array_values($row), str_repeat('s', count($columns)));
    }

    private function linkIntakeToBooking(int $intakeId, int $bookingId): void
    {
        $this->db->execute(
            'UPDATE bolt_mail_intake SET linked_booking_id = ?, updated_at = NOW() WHERE id = ?',
            [$bookingId, $intakeId],
            'ii'
        );
    }

    private function filterForTable(string $table, array $row): array
    {
        $columns = $this->tableColumns($table);
        if (!$columns) {
            return [];
        }

        $filtered = [];
        foreach ($row as $key => $value) {
            if (!isset($columns[$key])) {
                continue;
            }
            if ($value === null && !$this->columnIsNullable($columns[$key])) {
                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }

    private function tableColumns(string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $table = $this->safeIdentifier($table);
        $rows = $this->db->fetchAll('SHOW COLUMNS FROM `' . $table . '`');
        $columns = [];
        foreach ($rows as $row) {
            $columns[(string)$row['Field']] = $row;
        }

        $cache[$table] = $columns;
        return $columns;
    }

    private function safeIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Unsafe SQL identifier: ' . $identifier);
        }

        return $identifier;
    }

    private function columnIsNullable(array $column): bool
    {
        return strtoupper((string)($column['Null'] ?? '')) === 'YES';
    }

    private function tripSignatureHash(array $row): string
    {
        $parts = [
            (string)($row['operator_raw'] ?? ''),
            $this->normalizePhone((string)($row['customer_mobile'] ?? '')),
            $this->lower(trim((string)($row['customer_name'] ?? ''))),
            $this->lower(trim((string)($row['driver_name'] ?? ''))),
            strtoupper(trim((string)($row['vehicle_plate'] ?? ''))),
            $this->lower(trim((string)($row['pickup_address'] ?? ''))),
            $this->lower(trim((string)($row['dropoff_address'] ?? ''))),
            (string)($row['parsed_pickup_at'] ?? ''),
        ];

        return hash('sha256', implode('|', $parts));
    }

    private function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/[^0-9+]/', '', $value) ?: '';
    }

    private function parsePrice(string $value): string
    {
        $value = trim(str_replace(['€', 'EUR', 'eur'], '', $value));
        $value = str_replace(',', '.', $value);
        if (!preg_match('/-?\d+(?:\.\d+)?/', $value, $match)) {
            return '0.00';
        }

        return number_format((float)$match[0], 2, '.', '');
    }

    private function mysqlDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function formatEdxeixDate(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('d/m/Y H:i', $timestamp);
    }

    private function publicIntakeSnapshot(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'message_hash' => $row['message_hash'] ?? null,
            'subject' => $row['subject'] ?? null,
            'received_at' => $row['received_at'] ?? null,
            'operator_raw' => $row['operator_raw'] ?? null,
            'customer_name' => $row['customer_name'] ?? null,
            'customer_mobile' => $row['customer_mobile'] ?? null,
            'driver_name' => $row['driver_name'] ?? null,
            'vehicle_plate' => $row['vehicle_plate'] ?? null,
            'pickup_address' => $row['pickup_address'] ?? null,
            'dropoff_address' => $row['dropoff_address'] ?? null,
            'parsed_pickup_at' => $row['parsed_pickup_at'] ?? null,
            'parsed_end_at' => $row['parsed_end_at'] ?? null,
            'safety_status' => $row['safety_status'] ?? null,
        ];
    }
}
