<?php

namespace Bridge\Receipts;

use Bridge\Config;
use Bridge\Database;
use Bridge\Mail\BoltMailDriverNotificationService;
use RuntimeException;
use Throwable;

final class AadeReceiptAutoIssuer
{
    private Database $db;
    private Config $config;
    private AadeReceiptPayloadBuilder $payloadBuilder;
    private AadeMyDataClient $client;
    private BoltMailDriverNotificationService $driverNotifier;

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->payloadBuilder = new AadeReceiptPayloadBuilder($config, $db);
        $this->client = new AadeMyDataClient((array)$config->get('receipts.aade_mydata', []));
        $this->driverNotifier = new BoltMailDriverNotificationService(
            $db,
            (array)$config->get('mail.driver_notifications', []),
            new \DateTimeZone((string)$config->get('app.timezone', 'Europe/Athens'))
        );
    }

    /**
     * Automatically issue the official AADE/myDATA receipt for a real Bolt-mail
     * booking and send the issued receipt PDF to the already-resolved driver.
     *
     * This method intentionally keeps duplicate, source, test/synthetic, and
     * AADE-only mode gates. It does not touch EDXEIX jobs or attempts.
     *
     * @return array<string,mixed>
     */
    public function issueAndEmailForBooking(int $bookingId, string $createdBy = 'auto-cron'): array
    {
        $out = [
            'enabled' => $this->isAutoEnabled(),
            'attempted' => false,
            'issued' => false,
            'emailed' => false,
            'status' => 'skipped',
            'booking_id' => $bookingId,
            'attempt_id' => null,
            'receipt_email_status' => null,
            'blockers' => [],
            'error' => null,
        ];

        if ($bookingId <= 0) {
            $out['blockers'][] = 'invalid_booking_id';
            return $out;
        }

        $gate = $this->autoGate($bookingId);
        $out['gate'] = $gate;
        if (!empty($gate['blockers'])) {
            $out['status'] = 'blocked';
            $out['blockers'] = $gate['blockers'];
            return $out;
        }

        try {
            $built = $this->payloadBuilder->buildForBookingId($bookingId);
            $summary = $built['summary'];
            $validation = $built['validation'];
            $xml = (string)$built['xml'];
            $xmlHash = (string)$built['xml_sha256'];

            $duplicate = $this->duplicateGate($bookingId, $xmlHash);
            if (!empty($duplicate['blockers'])) {
                $out['status'] = 'duplicate_blocked';
                $out['duplicate_gate'] = $duplicate;
                $out['blockers'] = $duplicate['blockers'];
                return $out;
            }

            if (empty($validation['ok_for_send_if_confirmed'])) {
                $out['status'] = 'payload_blocked';
                $out['blockers'] = array_values(array_map('strval', $validation['blockers'] ?? []));
                return $out;
            }

            $out['attempted'] = true;
            $result = $this->client->sendInvoicesXml($xml);
            $responseBody = (string)($result['response_body'] ?? '');
            unset($result['response_body']);

            $parsed = $this->parseAadeResponse($responseBody);
            $issued = !empty($result['ok']) && empty($parsed['errors']) && (!empty($parsed['mark']) || !empty($parsed['uid']));
            $status = $issued ? 'issued' : 'failed';

            $attemptId = $this->recordAttempt(
                $summary,
                $status,
                $result,
                (int)($result['http_status'] ?? 0),
                $xml,
                $parsed,
                (string)($result['error'] ?? ''),
                $createdBy
            );

            $out['attempt_id'] = $attemptId;
            $out['provider_status'] = $status;
            $out['http_status'] = (int)($result['http_status'] ?? 0);
            $out['response_sha256'] = $result['response_sha256'] ?? null;
            $out['response_bytes'] = (int)($result['response_bytes'] ?? 0);
            $out['mark_present'] = !empty($parsed['mark']);
            $out['uid_present'] = !empty($parsed['uid']);
            $out['qr_url_present'] = !empty($parsed['qr_url']);
            $out['raw_response_printed'] = false;

            if (!$issued) {
                $out['status'] = 'aade_failed';
                $out['error'] = $parsed['error_message'] ?? (string)($result['error'] ?? 'aade_send_failed');
                return $out;
            }

            $out['issued'] = true;
            $row = $this->rowForDriverReceipt($bookingId);
            $receipt = [
                'attempt_id' => $attemptId,
                'mark' => $parsed['mark'] ?? null,
                'uid' => $parsed['uid'] ?? null,
                'qr_url' => $parsed['qr_url'] ?? null,
                'http_status' => (int)($result['http_status'] ?? 0),
                'response_sha256' => $result['response_sha256'] ?? null,
                'issued_at' => date('Y-m-d H:i:s'),
                'total_amount' => (string)($summary['gross_amount'] ?? ''),
                'net_amount' => (string)($summary['net_amount'] ?? ''),
                'vat_amount' => (string)($summary['vat_amount'] ?? ''),
                'vat_rate' => (string)($summary['vat_rate_percent'] ?? '13'),
                'series' => (string)($summary['series'] ?? ''),
                'aa' => (string)($summary['aa'] ?? ''),
                'document_type' => (string)($summary['document_type'] ?? ''),
            ];

            $email = $this->driverNotifier->sendAadeIssuedReceiptForIntake((int)($summary['intake_id'] ?? 0), $row, $receipt);
            $out['receipt_email_status'] = $email['status'] ?? 'skipped';
            $out['receipt_email_reason'] = $email['reason'] ?? null;
            $out['receipt_email_error'] = $email['error'] ?? null;
            $out['emailed'] = ($email['status'] ?? '') === 'sent';
            $out['status'] = $out['emailed'] ? 'issued_and_emailed' : 'issued_email_not_sent';

            if (!empty($email['pdf_path'])) {
                $this->updateAttemptOfficialPdfPath($attemptId, (string)$email['pdf_path']);
                $out['official_pdf_path'] = $email['pdf_path'];
            }

            return $out;
        } catch (Throwable $e) {
            $out['status'] = 'error';
            $out['error'] = $e->getMessage();
            return $out;
        }
    }

    /** @return array<string,mixed> */
    private function autoGate(int $bookingId): array
    {
        $blockers = [];
        $aade = (array)$this->config->get('receipts.aade_mydata', []);
        $timezone = new \DateTimeZone((string)$this->config->get('app.timezone', 'Europe/Athens'));
        $now = new \DateTimeImmutable('now', $timezone);
        $pickupAt = null;

        if ((string)$this->config->get('receipts.mode', '') !== 'aade_mydata') {
            $blockers[] = 'receipts_mode_not_aade_mydata';
        }
        if (!filter_var($aade['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $blockers[] = 'aade_mydata_not_enabled';
        }
        if (!filter_var($aade['allow_send_invoices'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $blockers[] = 'allow_send_invoices_not_enabled';
        }
        if (!filter_var($aade['auto_send_invoices'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $blockers[] = 'auto_send_invoices_not_enabled';
        }
        $notBeforeRaw = trim((string)($aade['auto_issue_not_before'] ?? ''));
        $notBefore = null;
        if ($notBeforeRaw === '') {
            $blockers[] = 'auto_issue_not_before_not_configured';
        } else {
            try {
                $notBefore = new \DateTimeImmutable($notBeforeRaw, $timezone);
            } catch (\Throwable) {
                $blockers[] = 'auto_issue_not_before_invalid';
            }
        }
        if ((string)$this->config->get('mail.driver_notifications.receipt_pdf_mode', '') !== 'aade_mydata') {
            $blockers[] = 'driver_receipt_pdf_mode_not_aade_mydata';
        }
        if (!filter_var($this->config->get('mail.driver_notifications.official_receipt_email_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $blockers[] = 'official_receipt_email_not_enabled';
        }

        $booking = $this->db->fetchOne('SELECT * FROM normalized_bookings WHERE id=? LIMIT 1', [$bookingId], 'i');
        if (!is_array($booking)) {
            $blockers[] = 'booking_not_found';
            return ['enabled' => $this->isAutoEnabled(), 'blockers' => $blockers];
        }

        if ((string)($booking['source'] ?? '') !== 'bolt_mail') {
            $blockers[] = 'booking_source_not_bolt_mail';
        }
        if (!empty($booking['is_test_booking'])) {
            $blockers[] = 'test_booking_blocked';
        }
        if (!empty($booking['never_submit_live'])) {
            $blockers[] = 'never_submit_live_blocked';
        }
        if ((float)($booking['price'] ?? 0) <= 0) {
            $blockers[] = 'price_not_positive';
        }
        if ($notBefore instanceof \DateTimeImmutable) {
            $createdRaw = trim((string)($booking['created_at'] ?? ''));
            try {
                $createdAt = new \DateTimeImmutable($createdRaw, $timezone);
                if ($createdAt < $notBefore) {
                    $blockers[] = 'booking_created_before_auto_issue_window';
                }
            } catch (\Throwable) {
                $blockers[] = 'booking_created_at_invalid';
            }
        }

        /*
         * Official receipt timing rule:
         * normalized_bookings.started_at is the parsed Bolt pick-up time for
         * bolt_mail rows. Do not issue/send the AADE receipt before that time.
         * The earlier Bolt "Start time" from the email is ride context only.
         */
        $pickupRaw = trim((string)($booking['started_at'] ?? ''));
        if ($pickupRaw === '') {
            $blockers[] = 'pickup_time_missing';
        } else {
            try {
                $pickupAt = new \DateTimeImmutable($pickupRaw, $timezone);
                if ($now < $pickupAt) {
                    $blockers[] = 'pickup_time_not_reached';
                }
            } catch (\Throwable) {
                $blockers[] = 'pickup_time_invalid';
            }
        }

        $intake = $this->db->fetchOne('SELECT id, customer_name, safety_status, created_at FROM bolt_mail_intake WHERE linked_booking_id=? ORDER BY id DESC LIMIT 1', [$bookingId], 'i');
        if (!is_array($intake)) {
            $blockers[] = 'linked_mail_intake_not_found';
        } else {
            $customer = strtoupper((string)($intake['customer_name'] ?? ''));
            if (str_contains($customer, 'CABNET TEST') || str_contains($customer, 'DO NOT SUBMIT')) {
                $blockers[] = 'synthetic_or_test_intake_blocked';
            }
        }

        return [
            'enabled' => $this->isAutoEnabled(),
            'booking_id' => $bookingId,
            'intake_id' => isset($intake['id']) ? (int)$intake['id'] : null,
            'now' => $now->format('Y-m-d H:i:s T'),
            'pickup_time' => $pickupAt instanceof \DateTimeImmutable ? $pickupAt->format('Y-m-d H:i:s T') : null,
            'receipt_timing_rule' => 'send_at_or_after_pickup_time',
            'blockers' => array_values(array_unique($blockers)),
        ];
    }

    private function isAutoEnabled(): bool
    {
        return (string)$this->config->get('receipts.mode', '') === 'aade_mydata'
            && (bool)$this->config->get('receipts.aade_mydata.enabled', false)
            && (bool)$this->config->get('receipts.aade_mydata.allow_send_invoices', false)
            && (bool)$this->config->get('receipts.aade_mydata.auto_send_invoices', false)
            && trim((string)$this->config->get('receipts.aade_mydata.auto_issue_not_before', '')) !== ''
            && (string)$this->config->get('mail.driver_notifications.receipt_pdf_mode', '') === 'aade_mydata'
            && (bool)$this->config->get('mail.driver_notifications.official_receipt_email_enabled', false);
    }

    /** @return array<string,mixed> */
    private function duplicateGate(int $bookingId, string $xmlHash): array
    {
        $blockers = [];
        $issuedForBooking = 0;
        $issuedForHash = 0;

        $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM receipt_issuance_attempts WHERE normalized_booking_id=? AND provider='aade_mydata' AND provider_status='issued'", [$bookingId], 'i');
        $issuedForBooking = (int)($row['c'] ?? 0);

        $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM receipt_issuance_attempts WHERE request_payload_hash=? AND provider='aade_mydata' AND provider_status='issued'", [$xmlHash], 's');
        $issuedForHash = (int)($row['c'] ?? 0);

        if ($issuedForBooking > 0) {
            $blockers[] = 'already_issued_for_booking';
        }
        if ($issuedForHash > 0) {
            $blockers[] = 'already_issued_for_payload_hash';
        }

        return [
            'issued_for_booking' => $issuedForBooking,
            'issued_for_xml_hash' => $issuedForHash,
            'blocked' => $blockers !== [],
            'blockers' => $blockers,
        ];
    }

    /** @return array<string,mixed> */
    private function rowForDriverReceipt(int $bookingId): array
    {
        $booking = $this->db->fetchOne('SELECT * FROM normalized_bookings WHERE id=? LIMIT 1', [$bookingId], 'i') ?: [];
        $intake = $this->db->fetchOne('SELECT * FROM bolt_mail_intake WHERE linked_booking_id=? ORDER BY id DESC LIMIT 1', [$bookingId], 'i') ?: [];

        return array_merge($intake, [
            'customer_name' => $booking['customer_name'] ?? $intake['customer_name'] ?? '',
            'customer_mobile' => $intake['customer_mobile'] ?? '',
            'driver_name' => $booking['driver_name'] ?? $intake['driver_name'] ?? '',
            'vehicle_plate' => $booking['vehicle_plate'] ?? $intake['vehicle_plate'] ?? '',
            'pickup_address' => $booking['boarding_point'] ?? $booking['pickup_address'] ?? $intake['pickup_address'] ?? '',
            'dropoff_address' => $booking['disembark_point'] ?? $booking['destination_address'] ?? $intake['dropoff_address'] ?? '',
            'estimated_pickup_time_raw' => $intake['estimated_pickup_time_raw'] ?? $booking['started_at'] ?? '',
            'estimated_end_time_raw' => $intake['estimated_end_time_raw'] ?? $booking['ended_at'] ?? '',
            'estimated_price_raw' => $intake['estimated_price_raw'] ?? $booking['price'] ?? '',
            'message_hash' => $intake['message_hash'] ?? '',
            'operator_raw' => $intake['operator_raw'] ?? 'Fleet Mykonos LUXLIMO Ι Κ Ε||MYKONOS CAB',
        ]);
    }

    /** @param array<string,mixed> $summary @param array<string,mixed>|null $http @param array<string,mixed>|null $parsed */
    private function recordAttempt(array $summary, string $status, ?array $http, int $httpStatus, string $xml, ?array $parsed, string $error, string $createdBy): int
    {
        $responseMeta = null;
        if (is_array($http)) {
            $responseMeta = json_encode([
                'http_status' => $httpStatus,
                'response_sha256' => $http['response_sha256'] ?? null,
                'response_bytes' => $http['response_bytes'] ?? 0,
                'mark_present' => !empty($parsed['mark']),
                'uid_present' => !empty($parsed['uid']),
                'qr_url_present' => !empty($parsed['qr_url']),
                'raw_response_suppressed' => true,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $sql = "INSERT INTO receipt_issuance_attempts (
            intake_id, normalized_booking_id, source, provider, environment, receipt_mode, provider_status,
            issuer_vat_number, document_type, total_amount, net_amount, vat_amount, vat_rate, http_status,
            mark, uid, qr_url, request_payload_hash, request_payload_json, response_payload_json,
            error_code, error_message, created_by
        ) VALUES (?, ?, 'bolt_mail', 'aade_mydata', ?, 'aade_mydata', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, [
            isset($summary['intake_id']) ? (int)$summary['intake_id'] : null,
            (int)($summary['booking_id'] ?? 0),
            (string)$this->config->get('receipts.aade_mydata.environment', 'production'),
            $status,
            (string)($summary['issuer_vat_number'] ?? ''),
            (string)($summary['document_type'] ?? ''),
            (string)($summary['gross_amount'] ?? '0.00'),
            (string)($summary['net_amount'] ?? '0.00'),
            (string)($summary['vat_amount'] ?? '0.00'),
            (string)($summary['vat_rate_percent'] ?? '13'),
            $httpStatus,
            $parsed['mark'] ?? null,
            $parsed['uid'] ?? null,
            $parsed['qr_url'] ?? null,
            hash('sha256', $xml),
            json_encode(['xml_sha256' => hash('sha256', $xml), 'xml_bytes' => strlen($xml)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $responseMeta,
            $parsed['error_code'] ?? null,
            $parsed['error_message'] ?? $error,
            $createdBy,
        ], 'iissssssssisssssssss');
    }

    private function updateAttemptOfficialPdfPath(int $attemptId, string $path): void
    {
        if ($attemptId <= 0 || $path === '') {
            return;
        }
        $this->db->execute('UPDATE receipt_issuance_attempts SET official_pdf_path=? WHERE id=?', [$path, $attemptId], 'si');
    }

    /** @return array<string,mixed> */
    private function parseAadeResponse(string $xml): array
    {
        $out = ['mark' => null, 'uid' => null, 'qr_url' => null, 'errors' => [], 'error_code' => null, 'error_message' => null];
        if (trim($xml) === '') {
            $out['errors'][] = 'empty_response';
            $out['error_message'] = 'AADE returned an empty response.';
            return $out;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        if (!$loaded) {
            $out['errors'][] = 'response_xml_parse_failed';
            $out['error_message'] = 'AADE response XML could not be parsed.';
            return $out;
        }

        $out['mark'] = $this->firstXmlText($dom, ['invoiceMark', 'mark']);
        $out['uid'] = $this->firstXmlText($dom, ['invoiceUid', 'uid']);
        $out['qr_url'] = $this->firstXmlText($dom, ['qrUrl', 'qrCodeUrl', 'authenticationCode']);
        $out['error_code'] = $this->firstXmlText($dom, ['errorCode', 'code']);
        $out['error_message'] = $this->firstXmlText($dom, ['message', 'errorMessage']);

        if ($out['error_code'] !== null || $out['error_message'] !== null) {
            $out['errors'][] = 'aade_error_present';
        }
        return $out;
    }

    /** @param array<int,string> $names */
    private function firstXmlText(\DOMDocument $dom, array $names): ?string
    {
        $xp = new \DOMXPath($dom);
        foreach ($names as $name) {
            $nodes = $xp->query('//*[local-name()="' . str_replace('"', '', $name) . '"]');
            if ($nodes !== false && $nodes->length > 0) {
                $value = trim((string)$nodes->item(0)?->textContent);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }
}
