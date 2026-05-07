<?php

namespace Bridge\Mail;

use Bridge\Database;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class BoltMailDriverNotificationService
{
    private Database $db;
    /** @var array<string,mixed> */
    private array $config;
    private DateTimeZone $timezone;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(Database $db, array $config = [], ?DateTimeZone $timezone = null)
    {
        $this->db = $db;
        $this->config = $config;
        $this->timezone = $timezone ?? new DateTimeZone('Europe/Athens');
    }

    /**
     * Sends one idempotent driver notification for a newly imported Bolt pre-ride email.
     *
     * @param array<string,mixed> $row Parsed mail intake row, before/after DB insert.
     * @return array{status:string,recipient:?string,reason:?string,error:?string}
     */
    public function notifyForImportedMail(int $intakeId, array $row): array
    {
        if ($intakeId < 1) {
            return ['status' => 'skipped', 'recipient' => null, 'reason' => 'invalid_intake_id', 'error' => null];
        }

        $existing = $this->db->fetchOne(
            'SELECT notification_status, recipient_email, skip_reason, error_message FROM bolt_mail_driver_notifications WHERE intake_id = ? LIMIT 1',
            [$intakeId],
            'i'
        );
        if (is_array($existing)) {
            return [
                'status' => (string)($existing['notification_status'] ?? 'skipped'),
                'recipient' => $existing['recipient_email'] !== null ? (string)$existing['recipient_email'] : null,
                'reason' => $existing['skip_reason'] !== null ? (string)$existing['skip_reason'] : 'already_recorded',
                'error' => $existing['error_message'] !== null ? (string)$existing['error_message'] : null,
            ];
        }

        if (!$this->isEnabled()) {
            return ['status' => 'skipped', 'recipient' => null, 'reason' => 'driver_notifications_disabled', 'error' => null];
        }

        $driverName = trim((string)($row['driver_name'] ?? ''));
        $vehiclePlate = $this->normalizePlate((string)($row['vehicle_plate'] ?? ''));
        $customerName = trim((string)($row['customer_name'] ?? ''));
        $messageHash = trim((string)($row['message_hash'] ?? ''));
        $subject = $this->buildSubject($row);

        if ($this->looksLikeSyntheticOrTest($row)) {
            return $this->recordSkipped($intakeId, $messageHash, $driverName, $vehiclePlate, null, $subject, 'test_or_synthetic_email_suppressed');
        }

        $recipient = $this->resolveRecipientEmail($driverName, $vehiclePlate);
        if ($recipient === '') {
            return $this->recordSkipped($intakeId, $messageHash, $driverName, $vehiclePlate, null, $subject, 'driver_email_not_configured');
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->recordFailed($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'invalid_driver_email');
        }

        $fromEmail = trim((string)($this->config['from_email'] ?? 'bolt-bridge@gov.cabnet.app'));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->recordFailed($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'invalid_from_email');
        }

        $body = $this->buildPlainTextBody($intakeId, $row);
        $headers = $this->buildHeaders($fromEmail, trim((string)($this->config['from_name'] ?? 'Cabnet Bolt Bridge')));
        $encodedSubject = $this->encodeHeader($subject);

        try {
            $sent = @mail($recipient, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
        } catch (Throwable $e) {
            return $this->recordFailed($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, $e->getMessage());
        }

        if (!$sent) {
            return $this->recordFailed($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'php_mail_returned_false');
        }

        $now = (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s');
        $this->insertNotification([
            'intake_id' => $intakeId,
            'message_hash' => $messageHash !== '' ? $messageHash : null,
            'driver_name' => $driverName !== '' ? $driverName : null,
            'vehicle_plate' => $vehiclePlate !== '' ? $vehiclePlate : null,
            'recipient_email' => $recipient,
            'email_subject' => $subject,
            'notification_status' => 'sent',
            'skip_reason' => null,
            'error_message' => null,
            'sent_at' => $now,
        ]);

        return ['status' => 'sent', 'recipient' => $recipient, 'reason' => null, 'error' => null];
    }

    private function isEnabled(): bool
    {
        return filter_var($this->config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function looksLikeSyntheticOrTest(array $row): bool
    {
        $haystack = strtoupper(implode(' ', array_map(static fn($v): string => (string)$v, [
            $row['subject'] ?? '',
            $row['customer_name'] ?? '',
            $row['pickup_address'] ?? '',
            $row['dropoff_address'] ?? '',
            $row['operator_raw'] ?? '',
        ])));

        foreach (['CABNET TEST', 'DO NOT SUBMIT', 'SYNTHETIC', ' TEST '] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function resolveRecipientEmail(string $driverName, string $vehiclePlate): string
    {
        $driverEmails = is_array($this->config['driver_emails'] ?? null) ? $this->config['driver_emails'] : [];
        $plateEmails = is_array($this->config['vehicle_plate_emails'] ?? null) ? $this->config['vehicle_plate_emails'] : [];

        $driverKey = $this->normalizeKey($driverName);
        foreach ($driverEmails as $name => $email) {
            if ($this->normalizeKey((string)$name) === $driverKey) {
                return trim((string)$email);
            }
        }

        $plateKey = $this->normalizePlate($vehiclePlate);
        foreach ($plateEmails as $plate => $email) {
            if ($this->normalizePlate((string)$plate) === $plateKey) {
                return trim((string)$email);
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $row
     */
    private function buildSubject(array $row): string
    {
        $prefix = trim((string)($this->config['subject_prefix'] ?? 'Bolt pre-ride details'));
        if ($prefix === '') {
            $prefix = 'Bolt pre-ride details';
        }

        $pickupAt = trim((string)($row['parsed_pickup_at'] ?? $row['estimated_pickup_time_raw'] ?? ''));
        $pickup = $this->shortLocation((string)($row['pickup_address'] ?? ''));
        $dropoff = $this->shortLocation((string)($row['dropoff_address'] ?? ''));
        $route = trim($pickup . ($dropoff !== '' ? ' → ' . $dropoff : ''));

        $subject = $prefix;
        if ($pickupAt !== '') {
            $subject .= ' | ' . $pickupAt;
        }
        if ($route !== '') {
            $subject .= ' | ' . $route;
        }

        return $this->stripHeaderUnsafe($subject);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function buildPlainTextBody(int $intakeId, array $row): string
    {
        $lines = [];
        $lines[] = 'Bolt Ride Details — Driver Copy';
        $lines[] = 'Generated by gov.cabnet.app when the pre-ride email reached the bridge mailbox.';
        $lines[] = '';
        $lines[] = 'Operator: ' . $this->value($row, 'operator_raw');
        $lines[] = 'Customer: ' . $this->value($row, 'customer_name');
        $lines[] = 'Customer mobile: ' . $this->value($row, 'customer_mobile');
        $lines[] = '';
        $lines[] = 'Driver: ' . $this->value($row, 'driver_name');
        $lines[] = 'Vehicle: ' . $this->value($row, 'vehicle_plate');
        $lines[] = '';
        $lines[] = 'Pickup: ' . $this->value($row, 'pickup_address');
        $lines[] = 'Drop-off: ' . $this->value($row, 'dropoff_address');
        $lines[] = '';
        $lines[] = 'Start time: ' . $this->value($row, 'start_time_raw');
        $lines[] = 'Estimated pick-up time: ' . $this->value($row, 'estimated_pickup_time_raw');
        $lines[] = 'Estimated end time: ' . $this->value($row, 'estimated_end_time_raw');
        $lines[] = 'Estimated price: ' . $this->value($row, 'estimated_price_raw');
        $lines[] = '';
        $lines[] = 'Bridge intake ID: #' . $intakeId;
        $lines[] = 'Safety: this is an email copy only. No EDXEIX submission was performed by this notification.';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<int,string>
     */
    private function buildHeaders(string $fromEmail, string $fromName): array
    {
        $headers = [];
        $headers[] = 'From: ' . $this->formatMailbox($fromName, $fromEmail);

        $replyTo = trim((string)($this->config['reply_to'] ?? ''));
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $bcc = trim((string)($this->config['bcc'] ?? ''));
        if ($bcc !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'X-Cabnet-Bridge: bolt-mail-driver-notification';

        return $headers;
    }

    private function formatMailbox(string $name, string $email): string
    {
        $name = trim($this->stripHeaderUnsafe($name));
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        $value = $this->stripHeaderUnsafe($value);
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }
        return $value;
    }

    private function stripHeaderUnsafe(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    /**
     * @param array<string,mixed> $row
     */
    private function value(array $row, string $key): string
    {
        $value = trim((string)($row[$key] ?? ''));
        return $value !== '' ? $value : '-';
    }

    private function shortLocation(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > 48 ? mb_substr($value, 0, 45, 'UTF-8') . '…' : $value;
        }
        return strlen($value) > 48 ? substr($value, 0, 45) . '...' : $value;
    }

    private function normalizeKey(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }
        return strtolower($value);
    }

    private function normalizePlate(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value) ?? '');
    }

    private function recordSkipped(int $intakeId, string $messageHash, string $driverName, string $vehiclePlate, ?string $recipient, string $subject, string $reason): array
    {
        $this->insertNotification([
            'intake_id' => $intakeId,
            'message_hash' => $messageHash !== '' ? $messageHash : null,
            'driver_name' => $driverName !== '' ? $driverName : null,
            'vehicle_plate' => $vehiclePlate !== '' ? $vehiclePlate : null,
            'recipient_email' => $recipient,
            'email_subject' => $subject,
            'notification_status' => 'skipped',
            'skip_reason' => $reason,
            'error_message' => null,
            'sent_at' => null,
        ]);

        return ['status' => 'skipped', 'recipient' => $recipient, 'reason' => $reason, 'error' => null];
    }

    private function recordFailed(int $intakeId, string $messageHash, string $driverName, string $vehiclePlate, ?string $recipient, string $subject, string $error): array
    {
        $this->insertNotification([
            'intake_id' => $intakeId,
            'message_hash' => $messageHash !== '' ? $messageHash : null,
            'driver_name' => $driverName !== '' ? $driverName : null,
            'vehicle_plate' => $vehiclePlate !== '' ? $vehiclePlate : null,
            'recipient_email' => $recipient,
            'email_subject' => $subject,
            'notification_status' => 'failed',
            'skip_reason' => null,
            'error_message' => $error,
            'sent_at' => null,
        ]);

        return ['status' => 'failed', 'recipient' => $recipient, 'reason' => null, 'error' => $error];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function insertNotification(array $data): void
    {
        $sql = "INSERT INTO bolt_mail_driver_notifications (
            intake_id, message_hash, driver_name, vehicle_plate, recipient_email, email_subject,
            notification_status, skip_reason, error_message, sent_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->insert($sql, [
            (int)$data['intake_id'],
            $data['message_hash'],
            $data['driver_name'],
            $data['vehicle_plate'],
            $data['recipient_email'],
            $data['email_subject'],
            $data['notification_status'],
            $data['skip_reason'],
            $data['error_message'],
            $data['sent_at'],
        ], 'isssssssss');
    }
}
