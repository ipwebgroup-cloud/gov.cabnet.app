<?php

namespace Bridge\Mail;

use Bridge\Database;
use DateTimeImmutable;
use DateInterval;
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
        $driverIdentifier = trim((string)($row['driver_identifier'] ?? $row['driver_uuid'] ?? $row['external_driver_id'] ?? ''));
        $vehiclePlate = $this->normalizePlate((string)($row['vehicle_plate'] ?? ''));
        $customerName = trim((string)($row['customer_name'] ?? ''));
        $messageHash = trim((string)($row['message_hash'] ?? ''));
        $subject = $this->buildSubject($row);

        if ($this->looksLikeSyntheticOrTest($row)) {
            return $this->recordSkipped($intakeId, $messageHash, $driverName, $vehiclePlate, null, $subject, 'test_or_synthetic_email_suppressed');
        }

        $recipient = $this->resolveRecipientEmail($driverName, $driverIdentifier);
        if ($recipient === '') {
            return $this->recordSkipped($intakeId, $messageHash, $driverName, $vehiclePlate, null, $subject, 'driver_email_not_found_in_bolt_directory');
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->recordFailed($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'invalid_driver_email');
        }

        $fromEmail = trim((string)($this->config['from_email'] ?? 'bolt-bridge@gov.cabnet.app'));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->recordFailed($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'invalid_from_email');
        }

        $fromName = trim((string)($this->config['from_name'] ?? 'Cabnet Bolt Bridge'));
        $body = $this->buildPlainTextBody($intakeId, $row);
        $headers = $this->buildHeaders($fromEmail, $fromName);
        $encodedSubject = $this->encodeHeader($subject);

        try {
            $sent = @mail($recipient, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
        } catch (Throwable $e) {
            return $this->recordFailed($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, $e->getMessage());
        }

        if (!$sent) {
            return $this->recordFailed($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'php_mail_returned_false');
        }

        $receiptSubject = $this->buildReceiptSubject($row);
        $receiptResult = $this->sendReceiptEmail($recipient, $receiptSubject, $intakeId, $row, $fromEmail, $fromName);
        $receiptTotals = $this->receiptTotals($row);

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
            'receipt_subject' => $receiptSubject,
            'receipt_status' => $receiptResult['status'] ?? 'skipped',
            'receipt_skip_reason' => $receiptResult['reason'] ?? null,
            'receipt_error_message' => $receiptResult['error'] ?? null,
            'receipt_sent_at' => ($receiptResult['status'] ?? '') === 'sent' ? $now : null,
            'receipt_vat_rate' => $this->receiptVatPercent(),
            'receipt_total_amount' => $receiptTotals['gross'],
            'receipt_net_amount' => $receiptTotals['net'],
            'receipt_vat_amount' => $receiptTotals['vat'],
        ]);

        return [
            'status' => 'sent',
            'recipient' => $recipient,
            'reason' => null,
            'error' => null,
            'receipt_status' => $receiptResult['status'] ?? 'skipped',
            'receipt_error' => $receiptResult['error'] ?? null,
        ];
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

    private function resolveRecipientEmail(string $driverName, string $driverIdentifier = ''): string
    {
        $recipient = $this->resolveRecipientEmailFromBoltDirectory($driverName, $driverIdentifier);
        if ($recipient !== '') {
            return $recipient;
        }

        if ($this->shouldSyncReferenceOnMiss()) {
            $this->syncBoltReferenceDirectory();
            $recipient = $this->resolveRecipientEmailFromBoltDirectory($driverName, $driverIdentifier);
            if ($recipient !== '') {
                return $recipient;
            }
        }

        // Emergency fallback only. Normal production behavior should resolve from
        // mapping_drivers.driver_email synced from the Bolt driver API, matched by
        // immutable driver identifier when available or by the driver's own name.
        // Vehicle plate is intentionally not used because drivers may change cars.
        return $this->resolveRecipientEmailFromManualFallback($driverName);
    }

    private function resolveRecipientEmailFromBoltDirectory(string $driverName, string $driverIdentifier = ''): string
    {
        $columns = $this->tableColumns('mapping_drivers');
        if (!isset($columns['driver_email'])) {
            return '';
        }

        $driverIdentifier = trim($driverIdentifier);
        if ($driverIdentifier !== '') {
            foreach (['driver_identifier', 'external_driver_id', 'driver_uuid', 'individual_identifier', 'external_id'] as $identifierColumn) {
                if (!isset($columns[$identifierColumn])) {
                    continue;
                }
                $row = $this->fetchDriverDirectoryRow('`' . $identifierColumn . '` = ?', [$driverIdentifier], 's', $columns);
                $email = $this->emailFromDirectoryRow($row);
                if ($email !== '') {
                    return $email;
                }
            }
        }

        $driverName = trim($driverName);
        if ($driverName === '') {
            return '';
        }

        $nameColumns = array_values(array_filter([
            isset($columns['external_driver_name']) ? 'external_driver_name' : null,
            isset($columns['driver_name']) ? 'driver_name' : null,
            isset($columns['name']) ? 'name' : null,
        ]));

        foreach ($nameColumns as $nameColumn) {
            $row = $this->fetchDriverDirectoryRow('LOWER(TRIM(`' . $nameColumn . '`)) = LOWER(TRIM(?))', [$driverName], 's', $columns);
            $email = $this->emailFromDirectoryRow($row);
            if ($email !== '') {
                return $email;
            }
        }

        // Last safe fallback: compare normalized driver names in PHP across recent
        // directory rows. This still uses driver identity/name only; never plate.
        $row = $this->fetchDriverDirectoryRowByNormalizedName($driverName, $nameColumns, $columns);
        $email = $this->emailFromDirectoryRow($row);
        if ($email !== '') {
            return $email;
        }

        return '';
    }

    /**
     * @param array<string,bool> $columns
     * @param array<int,mixed> $params
     */
    private function fetchDriverDirectoryRow(string $whereSql, array $params, string $types, array $columns): ?array
    {
        $select = ['driver_email'];
        if (isset($columns['raw_payload_json'])) {
            $select[] = 'raw_payload_json';
        }
        if (isset($columns['last_seen_at'])) {
            $order = '`last_seen_at` DESC, `id` DESC';
        } elseif (isset($columns['updated_at'])) {
            $order = '`updated_at` DESC, `id` DESC';
        } else {
            $order = '`id` DESC';
        }

        $activeSql = '';
        if (isset($columns['is_active'])) {
            $activeSql = ' AND `is_active` = 1';
        }

        return $this->db->fetchOne(
            'SELECT `' . implode('`,`', $select) . '` FROM mapping_drivers WHERE ' . $whereSql . $activeSql . ' ORDER BY ' . $order . ' LIMIT 1',
            $params,
            $types
        );
    }


    /**
     * @param array<int,string> $nameColumns
     * @param array<string,bool> $columns
     */
    private function fetchDriverDirectoryRowByNormalizedName(string $driverName, array $nameColumns, array $columns): ?array
    {
        if (!$nameColumns) {
            return null;
        }

        $select = ['driver_email'];
        foreach ($nameColumns as $nameColumn) {
            $select[] = $nameColumn;
        }
        if (isset($columns['raw_payload_json'])) {
            $select[] = 'raw_payload_json';
        }

        $order = isset($columns['last_seen_at']) ? '`last_seen_at` DESC, `id` DESC' : (isset($columns['updated_at']) ? '`updated_at` DESC, `id` DESC' : '`id` DESC');
        $activeSql = isset($columns['is_active']) ? ' AND `is_active` = 1' : '';
        $target = $this->normalizeHumanName($driverName);
        if ($target === '') {
            return null;
        }

        $rows = $this->db->fetchAll(
            "SELECT `" . implode('`,`', array_values(array_unique($select))) . "` FROM mapping_drivers WHERE driver_email IS NOT NULL AND driver_email <> ''" . $activeSql . ' ORDER BY ' . $order . ' LIMIT 300'
        );

        foreach ($rows as $row) {
            foreach ($nameColumns as $nameColumn) {
                if ($this->normalizeHumanName((string)($row[$nameColumn] ?? '')) === $target) {
                    return $row;
                }
            }
        }

        return null;
    }

    private function emailFromDirectoryRow(?array $row): string
    {
        if (!is_array($row)) {
            return '';
        }

        $email = trim((string)($row['driver_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $raw = trim((string)($row['raw_payload_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $email = $this->findEmailInPayload($decoded);
                if ($email !== '') {
                    return $email;
                }
            }
        }

        return '';
    }

    private function shouldSyncReferenceOnMiss(): bool
    {
        return filter_var($this->config['sync_reference_on_miss'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function syncBoltReferenceDirectory(): void
    {
        $lib = dirname(__DIR__, 2) . '/lib/bolt_sync_lib.php';
        if (is_file($lib)) {
            require_once $lib;
        }

        if (!function_exists('gov_bolt_sync_reference')) {
            return;
        }

        $hoursBack = (int)($this->config['sync_reference_hours_back'] ?? 720);
        $hoursBack = max(24, min(8760, $hoursBack));

        try {
            gov_bolt_sync_reference($hoursBack, false);
        } catch (Throwable) {
            // Notification lookup must never block mail intake. A failed Bolt
            // reference refresh simply falls through to the normal skipped audit.
        }
    }

    private function resolveRecipientEmailFromManualFallback(string $driverName): string
    {
        $driverEmails = is_array($this->config['manual_driver_emails'] ?? null) ? $this->config['manual_driver_emails'] : [];

        // Backward compatibility with the first v4.5 config draft. Driver-name
        // entries remain an emergency fallback. Vehicle/plate fallbacks are ignored
        // on purpose because drivers may use different cars at any time.
        if (!$driverEmails && is_array($this->config['driver_emails'] ?? null)) {
            $driverEmails = $this->config['driver_emails'];
        }

        $driverKey = $this->normalizeKey($driverName);
        foreach ($driverEmails as $name => $email) {
            if ($this->normalizeKey((string)$name) === $driverKey) {
                return trim((string)$email);
            }
        }

        return '';
    }

    /** @return array<string,bool> */
    private function tableColumns(string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $rows = $this->db->fetchAll('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        $columns = [];
        foreach ($rows as $row) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
        $cache[$table] = $columns;
        return $columns;
    }

    /** @param array<string,mixed> $payload */
    private function findEmailInPayload(array $payload): string
    {
        $stack = [$payload];
        while ($stack) {
            $item = array_pop($stack);
            if (!is_array($item)) {
                continue;
            }
            foreach ($item as $key => $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                    continue;
                }
                if (!is_string($value)) {
                    continue;
                }
                if (!str_contains(strtolower((string)$key), 'email')) {
                    continue;
                }
                $candidate = trim($value);
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    return $candidate;
                }
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
        $lines[] = 'Estimated end time: ' . $this->driverCopyEstimatedEndTime($row);
        $lines[] = 'Estimated price: ' . $this->driverCopyEstimatedPrice($row);
        $lines[] = '';
        $lines[] = 'Bridge intake ID: #' . $intakeId;
        $lines[] = 'Safety: this is an email copy only. No EDXEIX submission was performed by this notification.';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string,mixed> $row
     */
    private function buildReceiptSubject(array $row): string
    {
        $prefix = trim((string)($this->config['receipt_subject_prefix'] ?? 'Bolt pre-ride receipt'));
        if ($prefix === '') {
            $prefix = 'Bolt pre-ride receipt';
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
     * @return array{status:string,reason:?string,error:?string}
     */
    private function sendReceiptEmail(string $recipient, string $subject, int $intakeId, array $row, string $fromEmail, string $fromName): array
    {
        if (!$this->receiptCopyEnabled()) {
            return ['status' => 'skipped', 'reason' => 'receipt_copy_disabled', 'error' => null];
        }

        $body = $this->buildReceiptHtmlBody($intakeId, $row);
        $encodedBody = chunk_split(base64_encode($body), 76, "\r\n");
        $headers = $this->buildHeaders($fromEmail, $fromName, 'text/html', 'bolt-mail-driver-receipt', 'base64');

        try {
            $sent = @mail($recipient, $this->encodeHeader($subject), $encodedBody, implode("\r\n", $headers), '-f' . $fromEmail);
        } catch (Throwable $e) {
            return ['status' => 'failed', 'reason' => null, 'error' => $e->getMessage()];
        }

        if (!$sent) {
            return ['status' => 'failed', 'reason' => null, 'error' => 'php_mail_returned_false'];
        }

        return ['status' => 'sent', 'reason' => null, 'error' => null];
    }

    private function receiptCopyEnabled(): bool
    {
        return filter_var($this->config['receipt_copy_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function buildReceiptHtmlBody(int $intakeId, array $row): string
    {
        $logoUrl = trim((string)($this->config['receipt_logo_url'] ?? 'https://gov.cabnet.app/assets/logos/lux-limo-logo.jpeg'));
        $stampUrl = trim((string)($this->config['receipt_stamp_url'] ?? 'https://gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg'));
        $price = $this->driverCopyEstimatedPrice($row);
        $totals = $this->receiptTotals($row);
        $currency = (string)($totals['currency'] ?? 'EUR');
        $vatPercent = $this->receiptVatPercent();

        $totalLine = $totals['gross'] !== null ? $this->formatMoney((float)$totals['gross'], $currency) : $this->eh($price);
        $netLine = $totals['net'] !== null ? $this->formatMoney((float)$totals['net'], $currency) : 'Not available';
        $vatLine = $totals['vat'] !== null ? $this->formatMoney((float)$totals['vat'], $currency) : 'Not available';

        $customerName = $this->value($row, 'customer_name');
        $driverName = $this->value($row, 'driver_name');
        $vehiclePlate = $this->value($row, 'vehicle_plate');
        $pickup = $this->value($row, 'pickup_address');
        $dropoff = $this->value($row, 'dropoff_address');
        $pickupTime = $this->value($row, 'estimated_pickup_time_raw');
        $endTime = $this->driverCopyEstimatedEndTime($row);

        $rows = [
            'Operator' => $this->value($row, 'operator_raw'),
            'Customer' => $customerName,
            'Customer mobile' => $this->value($row, 'customer_mobile'),
            'Driver' => $driverName,
            'Vehicle' => $vehiclePlate,
            'Pickup' => $pickup,
            'Drop-off' => $dropoff,
            'Start time' => $this->value($row, 'start_time_raw'),
            'Pick-up time' => $pickupTime,
            'End time' => $endTime,
            'Price' => $price,
        ];

        $detailsHtml = '';
        foreach ($rows as $label => $value) {
            $detailsHtml .= '<tr>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e6eef5;color:#64748b;font-size:13px;width:32%;font-weight:bold;vertical-align:top;">' . $this->eh($label) . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e6eef5;color:#0f172a;font-size:14px;vertical-align:top;">' . $this->eh($value) . '</td>'
                . '</tr>';
        }

        $logoHtml = '';
        if ($logoUrl !== '') {
            $logoHtml = '<img src="' . $this->eh($logoUrl) . '" alt="LUX LIMO" style="display:block;margin:0 auto 12px auto;width:210px;max-width:80%;height:auto;border:0;outline:none;text-decoration:none;">';
        }

        $stampHtml = '';
        if ($stampUrl !== '') {
            $stampHtml = '<img src="' . $this->eh($stampUrl) . '" alt="LUX LIMO MYKONOS company stamp" style="display:block;margin:0 auto;width:300px;max-width:92%;height:auto;border:1px solid #e2e8f0;border-radius:8px;padding:8px;background:#ffffff;">';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bolt Ride Receipt Copy</title></head>'
            . '<body style="margin:0;padding:0;background:#eef4f8;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef4f8;width:100%;border-collapse:collapse;">'
            . '<tr><td align="center" style="padding:22px 12px;">'
            . '<table role="presentation" width="720" cellspacing="0" cellpadding="0" border="0" style="width:720px;max-width:100%;background:#ffffff;border:1px solid #cfe0eb;border-radius:16px;overflow:hidden;border-collapse:separate;box-shadow:0 10px 28px rgba(15,23,42,0.10);">'
            . '<tr><td style="padding:26px 26px 18px 26px;text-align:center;background:#ffffff;">'
            . $logoHtml
            . '<div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#0ea5c6;font-weight:bold;">LUX LIMO MYKONOS</div>'
            . '<h1 style="margin:8px 0 4px 0;font-size:24px;line-height:1.25;color:#0f172a;font-weight:700;">Bolt Ride Receipt Copy</h1>'
            . '<div style="font-size:13px;color:#64748b;line-height:1.45;">Generated by gov.cabnet.app when the pre-ride email reached the bridge mailbox.</div>'
            . '</td></tr>'
            . '<tr><td style="height:5px;background:#0ea5c6;line-height:5px;font-size:1px;">&nbsp;</td></tr>'
            . '<tr><td style="padding:22px 26px 8px 26px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">'
            . '<tr>'
            . '<td style="width:33.33%;padding:7px;vertical-align:top;"><div style="background:#f8fcff;border:1px solid #d7e9f3;border-radius:12px;padding:13px 12px;"><div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:bold;">Driver</div><div style="font-size:15px;color:#0f172a;font-weight:bold;margin-top:4px;">' . $this->eh($driverName) . '</div></div></td>'
            . '<td style="width:33.33%;padding:7px;vertical-align:top;"><div style="background:#f8fcff;border:1px solid #d7e9f3;border-radius:12px;padding:13px 12px;"><div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:bold;">Vehicle</div><div style="font-size:15px;color:#0f172a;font-weight:bold;margin-top:4px;">' . $this->eh($vehiclePlate) . '</div></div></td>'
            . '<td style="width:33.33%;padding:7px;vertical-align:top;"><div style="background:#f8fcff;border:1px solid #d7e9f3;border-radius:12px;padding:13px 12px;"><div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:bold;">Total</div><div style="font-size:15px;color:#0f172a;font-weight:bold;margin-top:4px;">' . $this->eh($totalLine) . '</div></div></td>'
            . '</tr></table>'
            . '</td></tr>'
            . '<tr><td style="padding:8px 26px 4px 26px;">'
            . '<div style="border:1px solid #d7e9f3;border-radius:14px;overflow:hidden;">'
            . '<div style="background:#f8fcff;padding:14px 16px;border-bottom:1px solid #d7e9f3;">'
            . '<div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:bold;">Route</div>'
            . '<div style="font-size:16px;line-height:1.45;color:#0f172a;margin-top:4px;"><strong>' . $this->eh($pickup) . '</strong><br><span style="color:#0ea5c6;font-weight:bold;">↓</span><br><strong>' . $this->eh($dropoff) . '</strong></div>'
            . '</div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">' . $detailsHtml . '</table>'
            . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 26px 4px 26px;">'
            . '<div style="background:#f8fcff;border:1px solid #d7e9f3;border-radius:14px;overflow:hidden;">'
            . '<div style="background:#0f172a;color:#ffffff;padding:14px 16px;font-size:17px;font-weight:bold;">VAT / TAX included in total</div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;font-size:14px;">'
            . '<tr><td style="padding:12px 14px;border-bottom:1px solid #e6eef5;color:#64748b;font-weight:bold;">Total, VAT included</td><td style="padding:12px 14px;border-bottom:1px solid #e6eef5;text-align:right;color:#0f172a;font-weight:bold;">' . $this->eh($totalLine) . '</td></tr>'
            . '<tr><td style="padding:12px 14px;border-bottom:1px solid #e6eef5;color:#64748b;font-weight:bold;">Net amount before VAT</td><td style="padding:12px 14px;border-bottom:1px solid #e6eef5;text-align:right;color:#0f172a;">' . $this->eh($netLine) . '</td></tr>'
            . '<tr><td style="padding:12px 14px;color:#64748b;font-weight:bold;">VAT / TAX included (' . $this->eh(number_format($vatPercent, 2, '.', '')) . '%)</td><td style="padding:12px 14px;text-align:right;color:#0f172a;">' . $this->eh($vatLine) . '</td></tr>'
            . '</table>'
            . '<div style="padding:0 14px 14px 14px;color:#64748b;font-size:12px;line-height:1.45;">VAT is calculated as included in the total at 13%. If the final Bolt amount changes, the tax values should be recalculated from the completed ride total.</div>'
            . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 26px 8px 26px;text-align:center;">'
            . '<div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:bold;margin-bottom:10px;">Company stamp</div>'
            . $stampHtml
            . '</td></tr>'
            . '<tr><td style="padding:10px 26px 24px 26px;">'
            . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:13px 14px;font-size:13px;color:#334155;line-height:1.55;">'
            . '<strong>Bridge intake ID:</strong> #' . $this->eh((string)$intakeId) . '<br>'
            . '<strong>Safety:</strong> this receipt copy is an email notification only. No EDXEIX submission was performed by this notification.'
            . '</div>'
            . '<div style="text-align:center;font-size:11px;color:#94a3b8;line-height:1.45;margin-top:14px;">LUX LIMO I.K.E. / MYKONOS CAB · Mykonos, Greece<br>Phone / WhatsApp: (+30) 694 654 0444</div>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr></table>'
            . '</body></html>';
    }

    /**
     * @return array<int,string>
     */
    private function buildHeaders(string $fromEmail, string $fromName, string $contentType = 'text/plain', string $bridgeHeader = 'bolt-mail-driver-notification', string $transferEncoding = '8bit'): array
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

        $safeContentType = $contentType === 'text/html' ? 'text/html' : 'text/plain';
        $safeEncoding = strtolower(trim($transferEncoding));
        if (!in_array($safeEncoding, ['8bit', 'base64', 'quoted-printable'], true)) {
            $safeEncoding = '8bit';
        }

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: ' . $safeContentType . '; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: ' . $safeEncoding;
        $headers[] = 'X-Cabnet-Bridge: ' . $this->stripHeaderUnsafe($bridgeHeader);

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

    /**
     * Driver-facing copy rule only: show the estimated end time as exactly
     * 30 minutes after the estimated pick-up time. This does not change the
     * stored intake row, normalized booking, dry-run evidence, or EDXEIX payload.
     *
     * @param array<string,mixed> $row
     */
    private function driverCopyEstimatedEndTime(array $row): string
    {
        $pickupRaw = trim((string)($row['estimated_pickup_time_raw'] ?? ''));
        if ($pickupRaw === '') {
            $pickupRaw = trim((string)($row['parsed_pickup_at'] ?? ''));
        }

        $derived = $this->deriveEndTimeThirtyMinutesAfter($pickupRaw);
        if ($derived !== '') {
            return $derived;
        }

        return $this->value($row, 'estimated_end_time_raw');
    }

    private function deriveEndTimeThirtyMinutesAfter(string $pickupRaw): string
    {
        $pickupRaw = trim($pickupRaw);
        if ($pickupRaw === '') {
            return '';
        }

        if (!preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})(?:\s+([A-Z]{2,6}))?/u', $pickupRaw, $m)) {
            return '';
        }

        $datePart = $m[1];
        $zoneLabel = isset($m[2]) ? trim((string)$m[2]) : '';

        $pickup = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datePart, $this->timezone);
        if (!$pickup instanceof DateTimeImmutable) {
            return '';
        }

        $end = $pickup->add(new DateInterval('PT30M'));
        return $end->format('Y-m-d H:i:s') . ($zoneLabel !== '' ? ' ' . $zoneLabel : '');
    }

    /**
     * Driver-facing copy rule only: when Bolt provides a price range, show the
     * first value only, for example "40.00 - 44.00 eur" becomes "40.00 eur".
     *
     * @param array<string,mixed> $row
     */
    private function driverCopyEstimatedPrice(array $row): string
    {
        $raw = trim((string)($row['estimated_price_raw'] ?? ''));
        if ($raw === '') {
            return '-';
        }

        $normalized = preg_replace('/\s+/u', ' ', $raw) ?? $raw;
        $normalized = trim($normalized);

        if (preg_match('/^([€$£]?\s*\d+(?:[.,]\d{1,2})?)\s*(?:-|–|—|to)\s*[€$£]?\s*\d+(?:[.,]\d{1,2})\s*([A-Z]{2,4}|€)?$/iu', $normalized, $m)) {
            $firstValue = trim(preg_replace('/\s+/u', '', (string)$m[1]) ?? (string)$m[1]);
            $currency = isset($m[2]) ? trim((string)$m[2]) : '';
            return trim($firstValue . ($currency !== '' ? ' ' . $currency : ''));
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{gross:?float,net:?float,vat:?float,currency:string}
     */
    private function receiptTotals(array $row): array
    {
        $price = $this->driverCopyEstimatedPrice($row);
        $gross = $this->parseMoneyAmount($price);
        $currency = $this->detectCurrency($price);

        if ($gross === null) {
            return ['gross' => null, 'net' => null, 'vat' => null, 'currency' => $currency];
        }

        $rate = $this->receiptVatPercent() / 100;
        $net = round($gross / (1 + $rate), 2);
        $vat = round($gross - $net, 2);

        return ['gross' => round($gross, 2), 'net' => $net, 'vat' => $vat, 'currency' => $currency];
    }

    private function receiptVatPercent(): float
    {
        $raw = $this->config['receipt_vat_rate_percent'] ?? $this->config['receipt_vat_rate'] ?? 13;
        $rate = is_numeric($raw) ? (float)$raw : 13.0;
        // Accept either percent format (13) or decimal format (0.13),
        // but store/display the percent value in the audit table.
        if ($rate > 0 && $rate <= 1) {
            $rate *= 100;
        }
        if ($rate <= 0 || $rate > 100) {
            return 13.0;
        }
        return $rate;
    }

    private function parseMoneyAmount(string $value): ?float
    {
        if (!preg_match('/(\d+(?:[.,]\d{1,2})?)/u', $value, $m)) {
            return null;
        }
        return (float)str_replace(',', '.', (string)$m[1]);
    }

    private function detectCurrency(string $value): string
    {
        if (preg_match('/\b(EUR|USD|GBP)\b/iu', $value, $m)) {
            return strtoupper((string)$m[1]);
        }
        if (str_contains($value, '€')) {
            return 'EUR';
        }
        return 'EUR';
    }

    private function formatMoney(float $amount, string $currency): string
    {
        return number_format($amount, 2, '.', '') . ' ' . strtoupper($currency !== '' ? $currency : 'EUR');
    }

    private function eh(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

    private function normalizeHumanName(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        // Compare names, not vehicles: remove punctuation/spacing noise while
        // preserving Greek/Latin letters and numbers.
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
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
            'receipt_subject' => null,
            'receipt_status' => 'skipped',
            'receipt_skip_reason' => 'main_notification_skipped',
            'receipt_error_message' => null,
            'receipt_sent_at' => null,
            'receipt_vat_rate' => $this->receiptVatPercent(),
            'receipt_total_amount' => null,
            'receipt_net_amount' => null,
            'receipt_vat_amount' => null,
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
            'receipt_subject' => null,
            'receipt_status' => 'skipped',
            'receipt_skip_reason' => 'main_notification_failed',
            'receipt_error_message' => null,
            'receipt_sent_at' => null,
            'receipt_vat_rate' => $this->receiptVatPercent(),
            'receipt_total_amount' => null,
            'receipt_net_amount' => null,
            'receipt_vat_amount' => null,
        ]);

        return ['status' => 'failed', 'recipient' => $recipient, 'reason' => null, 'error' => $error];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function insertNotification(array $data): void
    {
        $tableColumns = $this->tableColumns('bolt_mail_driver_notifications');
        $fields = [
            'intake_id', 'message_hash', 'driver_name', 'vehicle_plate', 'recipient_email', 'email_subject',
            'notification_status', 'skip_reason', 'error_message', 'sent_at'
        ];
        $params = [
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
        ];
        $types = 'isssssssss';

        foreach ([
            'receipt_subject', 'receipt_status', 'receipt_skip_reason', 'receipt_error_message', 'receipt_sent_at',
            'receipt_vat_rate', 'receipt_total_amount', 'receipt_net_amount', 'receipt_vat_amount'
        ] as $optionalField) {
            if (!isset($tableColumns[$optionalField])) {
                continue;
            }
            $fields[] = $optionalField;
            $params[] = $data[$optionalField] ?? null;
            $types .= 's';
        }

        $quoted = array_map(static fn(string $field): string => '`' . str_replace('`', '``', $field) . '`', $fields);
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $sql = 'INSERT INTO bolt_mail_driver_notifications (' . implode(', ', $quoted) . ') VALUES (' . $placeholders . ')';

        $this->db->insert($sql, $params, $types);
    }
}
