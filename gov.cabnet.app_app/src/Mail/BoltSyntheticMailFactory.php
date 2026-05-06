<?php

declare(strict_types=1);

namespace Bridge\Mail;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class BoltSyntheticMailFactory
{
    private string $maildir;
    private DateTimeZone $timezone;

    public function __construct(string $maildir, ?DateTimeZone $timezone = null)
    {
        $this->maildir = rtrim($maildir, '/');
        $this->timezone = $timezone ?? new DateTimeZone('Europe/Athens');
    }

    /**
     * Creates a synthetic Bolt Ride details email in Maildir/new for parser and
     * preflight testing without a real Bolt rider transaction.
     *
     * @return array<string,mixed>
     */
    public function create(array $options = []): array
    {
        $newDir = $this->maildir . '/new';
        if (!is_dir($newDir)) {
            throw new RuntimeException('Maildir new folder does not exist: ' . $newDir);
        }
        if (!is_writable($newDir)) {
            throw new RuntimeException('Maildir new folder is not writable: ' . $newDir);
        }

        $leadMinutes = $this->boundedInt($options['lead_minutes'] ?? 15, 3, 1440);
        $durationMinutes = $this->boundedInt($options['duration_minutes'] ?? 30, 5, 240);

        $now = new DateTimeImmutable('now', $this->timezone);
        $start = $now->modify('+' . max(1, $leadMinutes - 1) . ' minutes');
        $pickup = $now->modify('+' . $leadMinutes . ' minutes');
        $end = $pickup->modify('+' . $durationMinutes . ' minutes');

        $customer = $this->safeLine((string)($options['customer_name'] ?? 'CABNET TEST DO NOT SUBMIT'));
        if (stripos($customer, 'CABNET TEST') !== 0) {
            throw new RuntimeException('Synthetic test customer_name must start with CABNET TEST.');
        }

        $mobile = $this->safeLine((string)($options['customer_mobile'] ?? '+300000000000'));
        $driver = $this->safeLine((string)($options['driver_name'] ?? 'Filippos Giannakopoulos'));
        $vehicle = strtoupper($this->safeLine((string)($options['vehicle_plate'] ?? 'EHA2545')));
        $pickupAddress = $this->safeLine((string)($options['pickup_address'] ?? 'Mikonos 846 00, Greece'));
        $dropoffAddress = $this->safeLine((string)($options['dropoff_address'] ?? 'Chora TEST, Mykonos Chora'));
        $price = $this->safeLine((string)($options['estimated_price'] ?? '0.00 eur'));
        $operator = $this->safeLine((string)($options['operator'] ?? 'Fleet Mykonos LUXLIMO Ι Κ Ε||MYKONOS CAB'));

        $testId = 'cabnet-test-' . $now->format('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $filename = time() . '.' . $testId . '.srv39.ipwebgroup.com';
        $path = $newDir . '/' . $filename;

        $email = $this->buildEmail([
            'test_id' => $testId,
            'operator' => $operator,
            'customer_name' => $customer,
            'customer_mobile' => $mobile,
            'driver_name' => $driver,
            'vehicle_plate' => $vehicle,
            'pickup_address' => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'start_time' => $this->boltTime($start),
            'pickup_time' => $this->boltTime($pickup),
            'end_time' => $this->boltTime($end),
            'estimated_price' => $price,
            'date_header' => $now->format('r'),
        ]);

        if (@file_put_contents($path, $email, LOCK_EX) === false) {
            throw new RuntimeException('Could not write synthetic email file: ' . $path);
        }
        @chmod($path, 0600);

        return [
            'ok' => true,
            'path' => $path,
            'basename' => $filename,
            'message_id' => '<' . $testId . '@gov.cabnet.app>',
            'pickup_at' => $pickup->format('Y-m-d H:i:s'),
            'pickup_raw' => $this->boltTime($pickup),
            'start_raw' => $this->boltTime($start),
            'end_raw' => $this->boltTime($end),
            'lead_minutes' => $leadMinutes,
            'duration_minutes' => $durationMinutes,
            'customer_name' => $customer,
            'driver_name' => $driver,
            'vehicle_plate' => $vehicle,
            'pickup_address' => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'estimated_price' => $price,
            'safety' => 'Synthetic maildir-only test. No Bolt API call and no EDXEIX live submit.',
        ];
    }

    /** @param array<string,string> $data */
    private function buildEmail(array $data): string
    {
        return "From: Bolt <greece@bolt.eu>\n"
            . "To: bolt-bridge@gov.cabnet.app\n"
            . "Subject: Ride details\n"
            . "Date: {$data['date_header']}\n"
            . "Message-ID: <{$data['test_id']}@gov.cabnet.app>\n"
            . "Content-Type: text/plain; charset=UTF-8\n\n"
            . "This email contains ride-related information, including personal data, shared for the sole purpose of enabling the licensed local operator to fulfil its legal and regulatory obligations.\n\n"
            . "Operator: {$data['operator']}\n\n"
            . "Customer: {$data['customer_name']}\n\n"
            . "Customer mobile: {$data['customer_mobile']}\n\n"
            . "Driver: {$data['driver_name']}\n\n"
            . "Vehicle: {$data['vehicle_plate']}\n\n"
            . "Pickup: {$data['pickup_address']}\n\n"
            . "Drop-off: {$data['dropoff_address']}\n\n"
            . "Start time: {$data['start_time']}\n\n"
            . "Estimated pick-up time: {$data['pickup_time']}\n\n"
            . "Estimated end time: {$data['end_time']}\n\n"
            . "Estimated price: {$data['estimated_price']}\n";
    }

    private function boltTime(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s') . ' EEST';
    }

    private function boundedInt(mixed $value, int $min, int $max): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => $min]]);
        return max($min, min($max, (int)$int));
    }

    private function safeLine(string $value): string
    {
        $value = trim(str_replace(["\r", "\n", "\0"], ' ', $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return mb_substr($value, 0, 255, 'UTF-8');
    }
}
