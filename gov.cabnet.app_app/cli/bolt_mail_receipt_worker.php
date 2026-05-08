<?php
/**
 * gov.cabnet.app — Bolt mail AADE receipt worker v6.2.8
 *
 * Purpose:
 * - Stabilize driver receipt delivery without depending on Bolt API finishing data.
 * - Use the pre-ride Bolt email as the authoritative receipt trigger/context.
 * - Create/link a local receipt booking from bolt_mail_intake when needed.
 * - Issue AADE/myDATA at/after parsed_pickup_at via existing duplicate gates.
 * - Email the driver through the existing driver receipt notifier.
 *
 * Safety:
 * - Does not call EDXEIX.
 * - Does not create submission_jobs.
 * - Does not create submission_attempts.
 * - Does not print credentials, tokens, cookies, mobile numbers, or raw mail bodies.
 */

declare(strict_types=1);

use Bridge\Config;
use Bridge\Database;
use Bridge\Receipts\AadeReceiptAutoIssuer;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$container = require __DIR__ . '/../src/bootstrap.php';
$config = $container['config'];
$db = $container['db'];

$options = getopt('', ['minutes::', 'limit::', 'dry-run', 'json', 'help']);

if (isset($options['help'])) {
    echo "Bolt Mail AADE Receipt Worker v6.2.8\n";
    echo "Usage: php bolt_mail_receipt_worker.php [--minutes=240] [--limit=25] [--dry-run] [--json]\n";
    echo "Safety: creates/links local receipt bookings and issues AADE only through existing gates; no EDXEIX jobs/calls.\n";
    exit(0);
}

$minutes = isset($options['minutes']) ? max(15, min(1440, (int)$options['minutes'])) : 240;
$limit = isset($options['limit']) ? max(1, min(200, (int)$options['limit'])) : 25;
$dryRun = array_key_exists('dry-run', $options);
$json = array_key_exists('json', $options);

$out = [
    'ok' => false,
    'script' => 'cli/bolt_mail_receipt_worker.php',
    'version' => 'v6.2.8',
    'started_at' => date('c'),
    'finished_at' => null,
    'minutes' => $minutes,
    'limit' => $limit,
    'dry_run' => $dryRun,
    'summary' => [
        'candidate_rows' => 0,
        'created_bookings' => 0,
        'linked_existing_bookings' => 0,
        'already_issued' => 0,
        'aade_attempted' => 0,
        'aade_issued' => 0,
        'aade_emailed' => 0,
        'aade_blocked_pickup_not_reached' => 0,
        'aade_blocked_duplicate' => 0,
        'aade_blocked_other' => 0,
        'aade_failed' => 0,
        'errors' => 0,
    ],
    'items' => [],
    'safety' => [
        'does_not_call_edxeix' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'does_not_store_bolt_api_payloads' => true,
        'uses_email_estimated_price_first_number' => true,
        'uses_existing_aade_duplicate_gates' => true,
        'uses_existing_driver_email_delivery' => true,
    ],
    'error' => null,
];

try {
    $worker = new BoltMailReceiptWorkerV628($db, $config);
    $result = $worker->run($minutes, $limit, $dryRun);
    $out = array_replace_recursive($out, $result);
    $out['ok'] = ($out['summary']['errors'] ?? 0) === 0;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
    $out['summary']['errors'] = ($out['summary']['errors'] ?? 0) + 1;
}

$out['finished_at'] = date('c');

if ($json) {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo '[' . $out['finished_at'] . '] Bolt Mail AADE Receipt Worker v6.2.8' . PHP_EOL;
    if (!empty($out['ok'])) {
        $s = $out['summary'];
        echo 'OK candidates=' . $s['candidate_rows']
            . ' created_bookings=' . $s['created_bookings']
            . ' linked_existing=' . $s['linked_existing_bookings']
            . ' already_issued=' . $s['already_issued']
            . ' aade_attempted=' . $s['aade_attempted']
            . ' aade_issued=' . $s['aade_issued']
            . ' aade_emailed=' . $s['aade_emailed']
            . ' pickup_not_reached=' . $s['aade_blocked_pickup_not_reached']
            . ' duplicate=' . $s['aade_blocked_duplicate']
            . ' blocked_other=' . $s['aade_blocked_other']
            . ' failed=' . $s['aade_failed']
            . ' errors=' . $s['errors'] . PHP_EOL;
        echo 'Safety: AADE receipts only; no EDXEIX calls; no submission_jobs; no submission_attempts.' . PHP_EOL;
    } else {
        echo 'ERROR: ' . ($out['error'] ?? 'unknown_error') . PHP_EOL;
    }
}

exit(!empty($out['ok']) ? 0 : 1);

final class BoltMailReceiptWorkerV628
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config
    ) {
    }

    /** @return array<string,mixed> */
    public function run(int $minutes, int $limit, bool $dryRun): array
    {
        $beforeJobs = $this->countTable('submission_jobs');
        $beforeAttempts = $this->countTable('submission_attempts');

        $summary = [
            'candidate_rows' => 0,
            'created_bookings' => 0,
            'linked_existing_bookings' => 0,
            'already_issued' => 0,
            'aade_attempted' => 0,
            'aade_issued' => 0,
            'aade_emailed' => 0,
            'aade_blocked_pickup_not_reached' => 0,
            'aade_blocked_duplicate' => 0,
            'aade_blocked_other' => 0,
            'aade_failed' => 0,
            'errors' => 0,
        ];

        $items = [];
        $rows = $this->candidateRows($minutes, $limit);
        $summary['candidate_rows'] = count($rows);
        $issuer = new AadeReceiptAutoIssuer($this->db, $this->config);

        foreach ($rows as $row) {
            $intakeId = (int)($row['id'] ?? 0);
            $item = [
                'intake_id' => $intakeId,
                'customer_name' => (string)($row['customer_name'] ?? ''),
                'driver_name' => (string)($row['driver_name'] ?? ''),
                'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
                'parsed_pickup_at' => (string)($row['parsed_pickup_at'] ?? ''),
                'estimated_price_raw' => (string)($row['estimated_price_raw'] ?? ''),
                'booking_id' => null,
                'status' => 'pending',
                'message' => '',
                'issue_result' => null,
            ];

            try {
                $bookingId = (int)($row['linked_booking_id'] ?? 0);

                if ($bookingId <= 0) {
                    if ($dryRun) {
                        $preview = $this->previewReceiptBookingFromIntake($row);
                        $item['status'] = $preview['ok'] ? 'dry_run_would_create_booking' : 'dry_run_blocked_booking_create';
                        $item['message'] = $preview['message'];
                        $item['preview'] = $preview;
                        $items[] = $item;
                        continue;
                    }

                    $create = $this->createOrLinkReceiptBookingFromIntake($intakeId);
                    if (empty($create['ok']) || empty($create['booking_id'])) {
                        $summary['errors']++;
                        $item['status'] = 'booking_create_failed';
                        $item['message'] = (string)($create['message'] ?? 'Unable to create/link receipt booking.');
                        $item['create_result'] = $create;
                        $items[] = $item;
                        continue;
                    }

                    $bookingId = (int)$create['booking_id'];
                    $item['booking_id'] = $bookingId;
                    $item['create_result'] = $create;
                    if (!empty($create['created'])) {
                        $summary['created_bookings']++;
                    } else {
                        $summary['linked_existing_bookings']++;
                    }
                } else {
                    $item['booking_id'] = $bookingId;
                    $summary['linked_existing_bookings']++;
                }

                if ($this->issuedReceiptExistsForBooking($bookingId)) {
                    $summary['already_issued']++;
                    $item['status'] = 'already_issued';
                    $item['message'] = 'Issued AADE receipt already exists for this booking.';
                    $items[] = $item;
                    continue;
                }

                if ($dryRun) {
                    $item['status'] = 'dry_run_would_issue_when_gate_allows';
                    $item['message'] = 'Booking is linked; AADE issue would be attempted in live mode, subject to existing pickup-time and duplicate gates.';
                    $items[] = $item;
                    continue;
                }

                $issue = $issuer->issueAndEmailForBooking($bookingId, 'bolt-mail-receipt-worker-v6.2.8');
                $item['issue_result'] = $issue;

                if (!empty($issue['attempted'])) {
                    $summary['aade_attempted']++;
                }
                if (!empty($issue['issued'])) {
                    $summary['aade_issued']++;
                }
                if (!empty($issue['emailed'])) {
                    $summary['aade_emailed']++;
                }

                $status = (string)($issue['status'] ?? '');
                $blockers = array_map('strval', (array)($issue['blockers'] ?? []));

                if ($status === 'issued_and_emailed') {
                    $item['status'] = 'issued_and_emailed';
                    $item['message'] = 'AADE receipt issued and emailed to driver.';
                } elseif ($status === 'issued_email_not_sent') {
                    $summary['aade_failed']++;
                    $summary['errors']++;
                    $item['status'] = 'issued_email_not_sent';
                    $item['message'] = 'AADE receipt issued but driver email was not sent. Review receipt_email_error/reason.';
                } elseif ($status === 'duplicate_blocked' || in_array('already_issued_for_booking', $blockers, true)) {
                    $summary['aade_blocked_duplicate']++;
                    $item['status'] = 'duplicate_blocked';
                    $item['message'] = 'Duplicate gate blocked issuing because receipt already exists.';
                } elseif (in_array('pickup_time_not_reached', $blockers, true)) {
                    $summary['aade_blocked_pickup_not_reached']++;
                    $item['status'] = 'pickup_time_not_reached';
                    $item['message'] = 'Receipt booking is ready; AADE send waits until parsed_pickup_at.';
                } elseif (in_array($status, ['aade_failed', 'error'], true)) {
                    $summary['aade_failed']++;
                    $summary['errors']++;
                    $item['status'] = $status;
                    $item['message'] = (string)($issue['error'] ?? 'AADE issue failed.');
                } else {
                    $summary['aade_blocked_other']++;
                    $item['status'] = $status !== '' ? $status : 'blocked';
                    $item['message'] = 'AADE receipt was not issued; inspect blockers.';
                }

                $items[] = $item;
            } catch (Throwable $e) {
                $summary['errors']++;
                $item['status'] = 'error';
                $item['message'] = $e->getMessage();
                $items[] = $item;
            }
        }

        $afterJobs = $this->countTable('submission_jobs');
        $afterAttempts = $this->countTable('submission_attempts');

        return [
            'summary' => $summary,
            'items' => $items,
            'safety' => [
                'does_not_call_edxeix' => true,
                'does_not_create_submission_jobs' => true,
                'does_not_create_submission_attempts' => true,
                'does_not_store_bolt_api_payloads' => true,
                'uses_email_estimated_price_first_number' => true,
                'uses_existing_aade_duplicate_gates' => true,
                'uses_existing_driver_email_delivery' => true,
                'submission_jobs_before' => $beforeJobs,
                'submission_jobs_after' => $afterJobs,
                'submission_attempts_before' => $beforeAttempts,
                'submission_attempts_after' => $afterAttempts,
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function candidateRows(int $minutes, int $limit): array
    {
        $minutes = max(15, min(1440, $minutes));
        $limit = max(1, min(200, $limit));
        $notBefore = trim((string)$this->config->get('receipts.aade_mydata.auto_issue_not_before', '1970-01-01 00:00:00'));

        return $this->db->fetchAll(
            "SELECT i.*
             FROM bolt_mail_intake i
             LEFT JOIN receipt_issuance_attempts r
                ON r.intake_id = i.id
               AND r.provider = 'aade_mydata'
               AND r.provider_status = 'issued'
             WHERE i.parse_status = 'parsed'
               AND i.created_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)
               AND i.created_at >= ?
               AND r.id IS NULL
               AND UPPER(COALESCE(i.customer_name,'')) NOT LIKE '%CABNET TEST%'
               AND UPPER(COALESCE(i.customer_name,'')) NOT LIKE '%DO NOT SUBMIT%'
             ORDER BY COALESCE(i.parsed_pickup_at, i.created_at) ASC, i.id ASC
             LIMIT {$limit}",
            [$notBefore],
            's'
        );
    }

    /** @return array<string,mixed> */
    private function previewReceiptBookingFromIntake(array $intake): array
    {
        try {
            $this->validateIntakeForReceiptBooking($intake);
            return [
                'ok' => true,
                'message' => 'Receipt-only normalized booking can be created from this Bolt mail intake.',
                'price' => $this->firstMoney((string)($intake['estimated_price_raw'] ?? '')),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /** @return array<string,mixed> */
    private function createOrLinkReceiptBookingFromIntake(int $intakeId): array
    {
        $this->db->beginTransaction();

        try {
            $intake = $this->db->fetchOne('SELECT * FROM bolt_mail_intake WHERE id=? LIMIT 1 FOR UPDATE', [$intakeId], 'i');
            if (!is_array($intake)) {
                throw new RuntimeException('Intake row not found.');
            }

            $this->validateIntakeForReceiptBooking($intake);

            $linkedBookingId = (int)($intake['linked_booking_id'] ?? 0);
            if ($linkedBookingId > 0) {
                $booking = $this->db->fetchOne('SELECT id FROM normalized_bookings WHERE id=? LIMIT 1', [$linkedBookingId], 'i');
                if (is_array($booking)) {
                    $this->db->commit();
                    return [
                        'ok' => true,
                        'created' => false,
                        'linked' => true,
                        'booking_id' => $linkedBookingId,
                        'message' => 'Intake row was already linked to a normalized booking.',
                    ];
                }
            }

            $normalized = $this->buildReceiptBookingPayload($intake);
            $existing = $this->db->fetchOne('SELECT id FROM normalized_bookings WHERE dedupe_hash=? LIMIT 1', [(string)$normalized['dedupe_hash']], 's');
            if (is_array($existing)) {
                $bookingId = (int)$existing['id'];
                $this->db->execute('UPDATE bolt_mail_intake SET linked_booking_id=?, updated_at=NOW() WHERE id=?', [$bookingId, $intakeId], 'ii');
                $this->db->commit();
                return [
                    'ok' => true,
                    'created' => false,
                    'linked' => true,
                    'booking_id' => $bookingId,
                    'message' => 'Existing receipt booking was linked to the intake row.',
                ];
            }

            $row = $this->filterForTable('normalized_bookings', $normalized);
            if (!$row) {
                throw new RuntimeException('No compatible normalized_bookings columns available.');
            }

            $columns = array_keys($row);
            $sql = 'INSERT INTO normalized_bookings (`' . implode('`,`', array_map([$this, 'safeIdentifier'], $columns)) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $bookingId = $this->db->insert($sql, array_values($row), str_repeat('s', count($row)));
            $this->db->execute('UPDATE bolt_mail_intake SET linked_booking_id=?, updated_at=NOW() WHERE id=?', [$bookingId, $intakeId], 'ii');

            $this->db->commit();
            return [
                'ok' => true,
                'created' => true,
                'linked' => true,
                'booking_id' => $bookingId,
                'message' => 'Receipt-only normalized booking was created from Bolt mail intake. No EDXEIX submission job was created.',
            ];
        } catch (Throwable $e) {
            try {
                $this->db->rollback();
            } catch (Throwable) {
            }
            return [
                'ok' => false,
                'created' => false,
                'linked' => false,
                'booking_id' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function validateIntakeForReceiptBooking(array $intake): void
    {
        if ((string)($intake['parse_status'] ?? '') !== 'parsed') {
            throw new RuntimeException('Intake row is not parsed.');
        }

        $required = [
            'customer_name' => 'customer_name_missing',
            'driver_name' => 'driver_name_missing',
            'vehicle_plate' => 'vehicle_plate_missing',
            'pickup_address' => 'pickup_address_missing',
            'dropoff_address' => 'dropoff_address_missing',
            'parsed_pickup_at' => 'pickup_time_missing',
            'estimated_price_raw' => 'estimated_price_missing',
        ];
        foreach ($required as $column => $error) {
            if (trim((string)($intake[$column] ?? '')) === '') {
                throw new RuntimeException($error);
            }
        }

        $customer = strtoupper((string)($intake['customer_name'] ?? ''));
        if (str_contains($customer, 'CABNET TEST') || str_contains($customer, 'DO NOT SUBMIT')) {
            throw new RuntimeException('synthetic_or_test_intake_blocked');
        }

        if ((float)$this->firstMoney((string)($intake['estimated_price_raw'] ?? '')) <= 0) {
            throw new RuntimeException('estimated_price_not_positive');
        }
    }

    /** @return array<string,mixed> */
    private function buildReceiptBookingPayload(array $intake): array
    {
        $intakeId = (int)($intake['id'] ?? 0);
        $customer = trim((string)($intake['customer_name'] ?? ''));
        $driver = trim((string)($intake['driver_name'] ?? ''));
        $plate = strtoupper(trim((string)($intake['vehicle_plate'] ?? '')));
        $pickup = trim((string)($intake['pickup_address'] ?? ''));
        $dropoff = trim((string)($intake['dropoff_address'] ?? ''));
        $pickupAt = $this->mysqlDate((string)($intake['parsed_pickup_at'] ?? ''));
        $endAt = $this->mysqlDate((string)($intake['parsed_end_at'] ?? ''));
        if ($endAt === null && $pickupAt !== null) {
            $endAt = date('Y-m-d H:i:s', strtotime($pickupAt . ' +30 minutes'));
        }
        $draftedAt = $this->mysqlDate((string)($intake['parsed_start_at'] ?? ''))
            ?: $this->mysqlDate((string)($intake['received_at'] ?? ''))
            ?: date('Y-m-d H:i:s');

        $signature = $this->tripSignatureHash($intake);
        $sourceTripId = 'mail:' . substr($signature, 0, 32);
        $dedupeHash = hash('sha256', 'normalized_bookings|bolt_mail|' . $signature);
        $price = $this->firstMoney((string)($intake['estimated_price_raw'] ?? ''));

        $snapshot = [
            'intake_id' => $intakeId,
            'customer_name' => $customer,
            'driver_name' => $driver,
            'vehicle_plate' => $plate,
            'pickup_address' => $pickup,
            'dropoff_address' => $dropoff,
            'parsed_pickup_at' => $pickupAt,
            'estimated_price_raw' => (string)($intake['estimated_price_raw'] ?? ''),
            'receipt_source' => 'bolt_mail_receipt_worker_v6_2_8',
            'edxeix_submission_not_created' => true,
        ];

        $payload = [
            'source' => 'bolt_mail',
            'source_system' => 'bolt_mail',
            'source_trip_id' => $sourceTripId,
            'source_booking_id' => 'mail-intake-' . $intakeId,
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
            'starting_point_key' => (string)$this->config->get('edxeix.default_starting_point_key', 'edra_mas'),
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
            'notes' => 'v6.2.8 Bolt mail AADE receipt booking from intake #' . $intakeId . '. No EDXEIX submission job created.',
            'is_scheduled' => 1,
            'edxeix_ready' => 0,
            'is_test_booking' => 0,
            'never_submit_live' => 0,
            'live_submit_block_reason' => 'aade_receipt_only_no_edxeix_job',
            'raw_payload_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'edxeix_payload_json' => null,
            'dedupe_hash' => $dedupeHash,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $payload['normalized_payload_json'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $payload;
    }

    private function issuedReceiptExistsForBooking(int $bookingId): bool
    {
        if ($bookingId <= 0) {
            return false;
        }
        $row = $this->db->fetchOne(
            "SELECT id FROM receipt_issuance_attempts WHERE normalized_booking_id=? AND provider='aade_mydata' AND provider_status='issued' ORDER BY id DESC LIMIT 1",
            [$bookingId],
            'i'
        );
        return is_array($row);
    }

    private function countTable(string $table): int
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return 0;
        }
        try {
            $row = $this->db->fetchOne('SELECT COUNT(*) AS c FROM `' . $table . '`');
            return (int)($row['c'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<string,mixed> */
    private function filterForTable(string $table, array $payload): array
    {
        $columns = $this->tableColumns($table);
        $filtered = [];
        foreach ($payload as $key => $value) {
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

    /** @return array<string,array<string,mixed>> */
    private function tableColumns(string $table): array
    {
        static $cache = [];
        $table = $this->safeIdentifier($table);
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        $rows = $this->db->fetchAll('SHOW COLUMNS FROM `' . $table . '`');
        $columns = [];
        foreach ($rows as $row) {
            $columns[(string)$row['Field']] = $row;
        }
        $cache[$table] = $columns;
        return $columns;
    }

    private function columnIsNullable(array $column): bool
    {
        return strtoupper((string)($column['Null'] ?? '')) === 'YES';
    }

    private function safeIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Unsafe SQL identifier: ' . $identifier);
        }
        return $identifier;
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

    private function firstMoney(string $value): string
    {
        $raw = str_ireplace(['€', 'eur'], '', $value);
        $raw = str_replace(',', '.', $raw);
        if (!preg_match('/-?\d+(?:\.\d+)?/', $raw, $m)) {
            return '0.00';
        }
        return number_format((float)$m[0], 2, '.', '');
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/[^0-9+]/', '', $value) ?: '';
    }

    private function lower(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }
        return strtolower($value);
    }

    private function tripSignatureHash(array $row): string
    {
        return hash('sha256', implode('|', [
            (string)($row['operator_raw'] ?? ''),
            $this->normalizePhone((string)($row['customer_mobile'] ?? '')),
            $this->lower((string)($row['customer_name'] ?? '')),
            $this->lower((string)($row['driver_name'] ?? '')),
            strtoupper(trim((string)($row['vehicle_plate'] ?? ''))),
            $this->lower((string)($row['pickup_address'] ?? '')),
            $this->lower((string)($row['dropoff_address'] ?? '')),
            (string)($row['parsed_pickup_at'] ?? ''),
        ]));
    }
}
