<?php

namespace Bridge\Mail;

use Bridge\Database;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class BoltPreRideImporter
{
    private Database $db;
    private BoltPreRideEmailParser $parser;
    private DateTimeZone $timezone;
    private int $futureGuardMinutes;

    public function __construct(Database $db, ?BoltPreRideEmailParser $parser = null, ?DateTimeZone $timezone = null, int $futureGuardMinutes = 30)
    {
        $this->db = $db;
        $this->timezone = $timezone ?? new DateTimeZone('Europe/Athens');
        $this->parser = $parser ?? new BoltPreRideEmailParser($this->timezone);
        $this->futureGuardMinutes = max(0, $futureGuardMinutes);
    }

    /**
     * @return array{inserted:int,duplicates:int,rejected:int,errors:int,files:int,items:array<int,array<string,mixed>>}
     */
    public function importFromScanner(BoltMaildirScanner $scanner, int $limit = 200, int $daysBack = 14): array
    {
        $summary = [
            'inserted' => 0,
            'duplicates' => 0,
            'rejected' => 0,
            'errors' => 0,
            'files' => 0,
            'items' => [],
        ];

        foreach ($scanner->candidateFiles($limit, $daysBack) as $file) {
            $summary['files']++;
            $raw = $scanner->readFile($file['path']);
            if (!is_string($raw)) {
                $summary['errors']++;
                $summary['items'][] = ['file' => $file['basename'], 'status' => 'error', 'message' => 'Could not read file'];
                continue;
            }

            try {
                $row = $this->parser->parse($raw, $file['path']);
                $result = $this->insertParsedRow($row);
                $summary[$result['summary_key']]++;
                $summary['items'][] = [
                    'file' => $file['basename'],
                    'status' => $result['status'],
                    'id' => $result['id'],
                    'message' => $result['message'],
                ];
            } catch (Throwable $e) {
                $summary['errors']++;
                $summary['items'][] = ['file' => $file['basename'], 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $summary;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:?int,status:string,summary_key:string,message:string}
     */
    public function insertParsedRow(array $row): array
    {
        $hash = (string)($row['message_hash'] ?? '');
        if ($hash === '') {
            return ['id' => null, 'status' => 'rejected', 'summary_key' => 'rejected', 'message' => 'Missing message hash'];
        }

        $existing = $this->db->fetchOne('SELECT id FROM bolt_mail_intake WHERE message_hash = ? LIMIT 1', [$hash], 's');
        if (is_array($existing)) {
            return ['id' => (int)$existing['id'], 'status' => 'duplicate', 'summary_key' => 'duplicates', 'message' => 'Already imported'];
        }

        [$parseStatus, $safetyStatus, $reason] = $this->classify($row);

        $sql = "INSERT INTO bolt_mail_intake (
            source_mailbox, source_path, source_basename, message_id, message_hash, subject, sender_email, received_at,
            operator_raw, customer_name, customer_mobile, driver_name, vehicle_plate, pickup_address, dropoff_address,
            start_time_raw, estimated_pickup_time_raw, estimated_end_time_raw, estimated_price_raw,
            parsed_start_at, parsed_pickup_at, parsed_end_at, timezone_label,
            parse_status, safety_status, rejection_reason
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            'bolt-bridge@gov.cabnet.app',
            $row['source_path'] ?? null,
            $row['source_basename'] ?? null,
            $row['message_id'] ?? null,
            $row['message_hash'] ?? null,
            $row['subject'] ?? null,
            $row['sender_email'] ?? null,
            $row['received_at'] ?? null,
            $row['operator_raw'] ?? null,
            $row['customer_name'] ?? null,
            $row['customer_mobile'] ?? null,
            $row['driver_name'] ?? null,
            $row['vehicle_plate'] ?? null,
            $row['pickup_address'] ?? null,
            $row['dropoff_address'] ?? null,
            $row['start_time_raw'] ?? null,
            $row['estimated_pickup_time_raw'] ?? null,
            $row['estimated_end_time_raw'] ?? null,
            $row['estimated_price_raw'] ?? null,
            $row['parsed_start_at'] ?? null,
            $row['parsed_pickup_at'] ?? null,
            $row['parsed_end_at'] ?? null,
            $row['timezone_label'] ?? null,
            $parseStatus,
            $safetyStatus,
            $reason,
        ];

        $id = $this->db->insert($sql, $params, str_repeat('s', count($params)));

        return [
            'id' => $id,
            'status' => $parseStatus,
            'summary_key' => $parseStatus === 'rejected' ? 'rejected' : 'inserted',
            'message' => $reason ?? 'Imported',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{0:string,1:string,2:?string}
     */
    private function classify(array $row): array
    {
        $missing = [];
        foreach ([
            'operator_raw' => 'operator',
            'customer_name' => 'customer',
            'customer_mobile' => 'customer mobile',
            'driver_name' => 'driver',
            'vehicle_plate' => 'vehicle',
            'pickup_address' => 'pickup',
            'dropoff_address' => 'drop-off',
            'parsed_pickup_at' => 'estimated pickup time',
        ] as $key => $label) {
            if (empty($row[$key])) {
                $missing[] = $label;
            }
        }

        if (empty($row['raw_text_has_bolt_markers'])) {
            return ['rejected', 'needs_review', 'Email does not contain the required Bolt ride markers.'];
        }

        if (!empty($missing)) {
            return ['needs_review', 'needs_review', 'Missing required fields: ' . implode(', ', $missing)];
        }

        $pickup = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$row['parsed_pickup_at'], $this->timezone);
        if (!$pickup instanceof DateTimeImmutable) {
            return ['needs_review', 'needs_review', 'Could not parse estimated pickup time.'];
        }

        $now = new DateTimeImmutable('now', $this->timezone);
        $guard = $now->modify('+' . $this->futureGuardMinutes . ' minutes');

        if ($pickup <= $now) {
            return ['parsed', 'blocked_past', 'Parsed successfully, but pickup time is already in the past.'];
        }

        if ($pickup <= $guard) {
            return ['parsed', 'blocked_too_soon', 'Parsed successfully, but pickup time is inside the future guard window.'];
        }

        return ['parsed', 'future_candidate', 'Parsed successfully as a future candidate. Live submission remains disabled.'];
    }
}
