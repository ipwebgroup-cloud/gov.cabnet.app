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


    /**
     * Sends the official AADE/myDATA issued receipt PDF after SendInvoices succeeds.
     * This bypasses receipt_copy_enabled because the generated/static fallback is
     * disabled; it only sends a PDF populated with official AADE MARK/UID/QR data.
     *
     * @param array<string,mixed> $row
     * @param array<string,mixed> $aadeReceipt
     * @return array{status:string,recipient:?string,reason:?string,error:?string,pdf_path?:string}
     */
    public function sendAadeIssuedReceiptForIntake(int $intakeId, array $row, array $aadeReceipt): array
    {
        if ($intakeId < 1) {
            return ['status' => 'skipped', 'recipient' => null, 'reason' => 'invalid_intake_id', 'error' => null];
        }
        if (!$this->isEnabled()) {
            return ['status' => 'skipped', 'recipient' => null, 'reason' => 'driver_notifications_disabled', 'error' => null];
        }
        if (!filter_var($this->config['official_receipt_email_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['status' => 'skipped', 'recipient' => null, 'reason' => 'official_receipt_email_disabled', 'error' => null];
        }
        if ($this->looksLikeSyntheticOrTest($row)) {
            return ['status' => 'skipped', 'recipient' => null, 'reason' => 'test_or_synthetic_email_suppressed', 'error' => null];
        }

        $existing = $this->db->fetchOne(
            "SELECT recipient_email, receipt_status FROM bolt_mail_driver_notifications WHERE intake_id=? ORDER BY id DESC LIMIT 1",
            [$intakeId],
            'i'
        );
        if (is_array($existing) && (string)($existing['receipt_status'] ?? '') === 'sent') {
            return [
                'status' => 'skipped',
                'recipient' => $existing['recipient_email'] !== null ? (string)$existing['recipient_email'] : null,
                'reason' => 'receipt_already_sent',
                'error' => null,
            ];
        }

        $driverName = trim((string)($row['driver_name'] ?? ''));
        $driverIdentifier = trim((string)($row['driver_identifier'] ?? $row['driver_uuid'] ?? $row['external_driver_id'] ?? ''));
        $vehiclePlate = $this->normalizePlate((string)($row['vehicle_plate'] ?? ''));
        $messageHash = trim((string)($row['message_hash'] ?? ''));
        $recipient = is_array($existing) && filter_var((string)($existing['recipient_email'] ?? ''), FILTER_VALIDATE_EMAIL)
            ? (string)$existing['recipient_email']
            : $this->resolveRecipientEmail($driverName, $driverIdentifier);

        $subject = $this->buildAadeReceiptSubject($row, $aadeReceipt);
        if ($recipient === '') {
            $this->upsertReceiptAudit($intakeId, $messageHash, $driverName, $vehiclePlate, null, $subject, 'failed', 'driver_email_not_found_in_bolt_directory', null, $aadeReceipt);
            return ['status' => 'failed', 'recipient' => null, 'reason' => null, 'error' => 'driver_email_not_found_in_bolt_directory'];
        }
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->upsertReceiptAudit($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'failed', 'invalid_driver_email', null, $aadeReceipt);
            return ['status' => 'failed', 'recipient' => $recipient, 'reason' => null, 'error' => 'invalid_driver_email'];
        }

        $fromEmail = trim((string)($this->config['from_email'] ?? 'bolt-bridge@gov.cabnet.app'));
        $fromName = trim((string)($this->config['from_name'] ?? 'Cabnet Bolt Bridge'));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'failed', 'recipient' => $recipient, 'reason' => null, 'error' => 'invalid_from_email'];
        }

        try {
            $pdfBytes = $this->buildAadeIssuedReceiptPdf($intakeId, $row, $aadeReceipt);
            $pdfName = $this->aadeReceiptPdfFilename($intakeId, $aadeReceipt);
            $pdfPath = $this->storeAadeReceiptPdf($pdfBytes, $pdfName);
            $send = $this->sendReceiptEmailWithPdfBytes($recipient, $subject, $intakeId, $row, $fromEmail, $fromName, $pdfBytes, $pdfName, 'bolt-mail-driver-aade-mydata-receipt-pdf');
        } catch (Throwable $e) {
            $this->upsertReceiptAudit($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'failed', $e->getMessage(), null, $aadeReceipt);
            return ['status' => 'failed', 'recipient' => $recipient, 'reason' => null, 'error' => $e->getMessage()];
        }

        if (($send['status'] ?? '') !== 'sent') {
            $this->upsertReceiptAudit($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'failed', (string)($send['error'] ?? $send['reason'] ?? 'receipt_email_send_failed'), null, $aadeReceipt);
            return ['status' => 'failed', 'recipient' => $recipient, 'reason' => null, 'error' => (string)($send['error'] ?? $send['reason'] ?? 'receipt_email_send_failed')];
        }

        $copyResults = [];
        foreach ($this->officialReceiptCopyRecipients() as $copyRecipient) {
            try {
                $copySubject = '[COPY] ' . $subject;
                $copySend = $this->sendReceiptEmailWithPdfBytes(
                    $copyRecipient,
                    $copySubject,
                    $intakeId,
                    $row,
                    $fromEmail,
                    $fromName,
                    $pdfBytes,
                    $pdfName,
                    'bolt-mail-office-aade-mydata-receipt-pdf-copy'
                );

                $copyResults[] = [
                    'recipient' => $copyRecipient,
                    'status' => (string)($copySend['status'] ?? 'unknown'),
                    'reason' => $copySend['reason'] ?? null,
                    'error' => $copySend['error'] ?? null,
                ];
            } catch (Throwable $e) {
                $copyResults[] = [
                    'recipient' => $copyRecipient,
                    'status' => 'failed',
                    'reason' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->upsertReceiptAudit($intakeId, $messageHash, $driverName, $vehiclePlate, $recipient, $subject, 'sent', null, (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s'), $aadeReceipt);

        $out = ['status' => 'sent', 'recipient' => $recipient, 'reason' => null, 'error' => null, 'pdf_path' => $pdfPath];
        if ($copyResults !== []) {
            $out['copy_results'] = $copyResults;
        }
        return $out;
    }

    /** @return array<int,string> */
    private function officialReceiptCopyRecipients(): array
    {
        if (!filter_var($this->config['official_receipt_copy_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $raw = $this->config['official_receipt_copy_emails'] ?? ($this->config['official_receipt_copy_email'] ?? []);
        $values = [];

        if (is_array($raw)) {
            foreach ($raw as $value) {
                $values[] = (string)$value;
            }
        } else {
            $values = preg_split('/[;,]+/', (string)$raw) ?: [];
        }

        $emails = [];
        foreach ($values as $value) {
            $email = trim($value);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[strtolower($email)] = $email;
            }
        }

        return array_values($emails);
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $aadeReceipt */
    private function buildAadeReceiptSubject(array $row, array $aadeReceipt): string
    {
        $prefix = trim((string)($this->config['aade_receipt_subject_prefix'] ?? 'AADE receipt'));
        if ($prefix === '') {
            $prefix = 'AADE receipt';
        }
        $mark = trim((string)($aadeReceipt['mark'] ?? ''));
        $route = trim($this->shortLocation((string)($row['pickup_address'] ?? '')) . ' → ' . $this->shortLocation((string)($row['dropoff_address'] ?? '')));
        $subject = $prefix;
        if ($mark !== '') {
            $subject .= ' | MARK ' . $mark;
        }
        if ($route !== '→' && $route !== '') {
            $subject .= ' | ' . $route;
        }
        return $this->stripHeaderUnsafe($subject);
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $aadeReceipt */
    private function buildAadeIssuedReceiptPdf(int $intakeId, array $row, array $aadeReceipt): string
    {
        $currency = 'EUR';
        $gross = $this->moneyFromReceipt($aadeReceipt, 'total_amount');
        $net = $this->moneyFromReceipt($aadeReceipt, 'net_amount');
        $vat = $this->moneyFromReceipt($aadeReceipt, 'vat_amount');
        $vatRate = (float)($aadeReceipt['vat_rate'] ?? $this->receiptVatPercent());
        $mark = trim((string)($aadeReceipt['mark'] ?? ''));
        $uid = trim((string)($aadeReceipt['uid'] ?? ''));
        $qrUrl = trim((string)($aadeReceipt['qr_url'] ?? ''));
        $series = trim((string)($aadeReceipt['series'] ?? 'BOLT'));
        $aa = trim((string)($aadeReceipt['aa'] ?? ''));
        $docType = trim((string)($aadeReceipt['document_type'] ?? '11.2'));
        $issuedAt = trim((string)($aadeReceipt['issued_at'] ?? date('Y-m-d H:i:s')));

        $pdf = new InternalReceiptPdfBuilder('LUX LIMO / MYKONOS CAB - AADE myDATA Receipt');
        $pdf->addPage();

        $logoPath = $this->localAssetPath((string)($this->config['receipt_logo_path'] ?? '/home/cabnet/public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg'));
        if ($logoPath !== '') {
            $pdf->imageJpeg($logoPath, 382, 735, 145, 0);
        }
        $stampPath = $this->localAssetPath((string)($this->config['receipt_stamp_path'] ?? '/home/cabnet/public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg'));
        if ($stampPath !== '') {
            $pdf->imageJpeg($stampPath, 365, 80, 135, 0);
        }

        $pdf->setFont('Helvetica', 'B', 18);
        $pdf->text(48, 760, 'LUX LIMO I.K.E.');
        $pdf->setFont('Helvetica', '', 9);
        $pdf->text(48, 744, 'AFM/VAT: 802653254');
        $pdf->text(48, 731, 'Tourist Office - Mykonos 84600, Greece');
        $pdf->text(48, 718, 'Phone / WhatsApp: (+30) 694 654 0444');

        $pdf->setFont('Helvetica', 'B', 15);
        $pdf->text(48, 682, 'AADE myDATA RECEIPT');
        $pdf->setFont('Helvetica', '', 9);
        $pdf->text(48, 666, 'Official AADE/myDATA transmission completed. Receipt data is based on MARK/UID returned by AADE.');

        $pdf->roundedRect(360, 626, 170, 74, 6, [238, 246, 250], [190, 210, 220]);
        $pdf->setFont('Helvetica', 'B', 9);
        $pdf->text(374, 680, 'AADE MARK');
        $pdf->setFont('Helvetica', 'B', 10);
        $pdf->textWrap(374, 664, 140, 11, $this->pdfSafeText($mark !== '' ? $mark : '-'), 8);
        $pdf->setFont('Helvetica', 'B', 9);
        $pdf->text(374, 640, 'Series / AA: ' . $this->pdfSafeText($series . ' / ' . $aa));
        $pdf->text(374, 626, 'Type: ' . $this->pdfSafeText($docType));

        $y = 615;
        $pdf->sectionTitle(48, $y, 'Transfer details');
        $y -= 24;
        foreach ([
            ['Passenger', $this->value($row, 'customer_name')],
            ['Customer mobile', $this->value($row, 'customer_mobile')],
            ['Driver', $this->value($row, 'driver_name')],
            ['Vehicle', $this->value($row, 'vehicle_plate')],
            ['Pickup', $this->value($row, 'pickup_address')],
            ['Drop-off', $this->value($row, 'dropoff_address')],
            ['Pick-up time', $this->value($row, 'estimated_pickup_time_raw')],
            ['End time', $this->driverCopyEstimatedEndTime($row)],
        ] as [$label, $value]) {
            $pdf->tableRow(48, $y, 500, (string)$label, $this->pdfSafeText((string)$value));
            $y -= 24;
        }

        $y -= 8;
        $pdf->sectionTitle(48, $y, 'VAT / TAX included in total');
        $y -= 26;
        $pdf->moneyRow(48, $y, 500, 'Net amount before VAT', $this->formatMoney($net, $currency));
        $y -= 24;
        $pdf->moneyRow(48, $y, 500, 'VAT / TAX included (' . number_format($vatRate, 2, '.', '') . '%)', $this->formatMoney($vat, $currency));
        $y -= 24;
        $pdf->moneyRow(48, $y, 500, 'Total, VAT included', $this->formatMoney($gross, $currency), true);

        $pdf->sectionTitle(48, 215, 'AADE myDATA verification');
        $payload = $qrUrl !== '' ? $qrUrl : ('AADE|MARK=' . $mark . '|UID=' . $uid . '|INTAKE=' . $intakeId);
        $pdf->drawQrCode(substr($payload, 0, 100), 48, 78, 96);
        $pdf->setFont('Helvetica', '', 7);
        $pdf->text(152, 196, 'UID: ' . $this->pdfSafeText($uid !== '' ? $uid : '-'));
        $pdf->textWrap(152, 182, 380, 10, 'QR URL / verification payload: ' . $this->pdfSafeText($payload), 7);
        $pdf->text(152, 138, 'Issued at: ' . $this->pdfSafeText($issuedAt));
        $pdf->text(152, 126, 'Bridge intake ID: #' . $intakeId);

        $pdf->setFont('Helvetica', '', 7);
        $pdf->text(48, 42, 'Generated by gov.cabnet.app after AADE/myDATA SendInvoices success. No EDXEIX submission is performed by this receipt email.');

        return $pdf->output();
    }

    /** @param array<string,mixed> $aadeReceipt */
    private function moneyFromReceipt(array $aadeReceipt, string $key): float
    {
        $raw = $aadeReceipt[$key] ?? '0.00';
        return round((float)str_replace(',', '.', (string)$raw), 2);
    }

    /** @param array<string,mixed> $aadeReceipt */
    private function aadeReceiptPdfFilename(int $intakeId, array $aadeReceipt): string
    {
        $mark = trim((string)($aadeReceipt['mark'] ?? ''));
        $base = $mark !== '' ? 'aade-mark-' . $mark : 'aade-receipt-intake-' . $intakeId;
        return $this->safeAttachmentFilename($base . '.pdf');
    }

    private function storeAadeReceiptPdf(string $pdfBytes, string $pdfName): string
    {
        $dir = '/home/cabnet/gov.cabnet.app_app/storage/receipt_attachments/aade';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create AADE receipt attachment directory.');
        }
        $path = $dir . '/' . $this->safeAttachmentFilename($pdfName);
        if (file_put_contents($path, $pdfBytes, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write AADE receipt PDF.');
        }
        @chmod($path, 0640);
        return $path;
    }

    /** @param array<string,mixed> $aadeReceipt */
    private function upsertReceiptAudit(int $intakeId, string $messageHash, string $driverName, string $vehiclePlate, ?string $recipient, string $subject, string $status, ?string $error, ?string $sentAt, array $aadeReceipt): void
    {
        $totals = [
            'gross' => $this->moneyFromReceipt($aadeReceipt, 'total_amount'),
            'net' => $this->moneyFromReceipt($aadeReceipt, 'net_amount'),
            'vat' => $this->moneyFromReceipt($aadeReceipt, 'vat_amount'),
        ];
        $existing = $this->db->fetchOne('SELECT id FROM bolt_mail_driver_notifications WHERE intake_id=? ORDER BY id DESC LIMIT 1', [$intakeId], 'i');
        if (is_array($existing)) {
            $this->db->execute(
                'UPDATE bolt_mail_driver_notifications SET recipient_email=COALESCE(recipient_email, ?), receipt_subject=?, receipt_status=?, receipt_skip_reason=?, receipt_error_message=?, receipt_sent_at=?, receipt_vat_rate=?, receipt_total_amount=?, receipt_net_amount=?, receipt_vat_amount=?, updated_at=? WHERE id=?',
                [
                    $recipient,
                    $subject,
                    $status,
                    $status === 'sent' ? null : ($error ?? 'official_receipt_not_sent'),
                    $status === 'sent' ? null : $error,
                    $sentAt,
                    (string)($aadeReceipt['vat_rate'] ?? $this->receiptVatPercent()),
                    number_format($totals['gross'], 2, '.', ''),
                    number_format($totals['net'], 2, '.', ''),
                    number_format($totals['vat'], 2, '.', ''),
                    (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s'),
                    (int)$existing['id'],
                ],
                'sssssssssssi'
            );
            return;
        }

        $this->insertNotification([
            'intake_id' => $intakeId,
            'message_hash' => $messageHash !== '' ? $messageHash : null,
            'driver_name' => $driverName !== '' ? $driverName : null,
            'vehicle_plate' => $vehiclePlate !== '' ? $vehiclePlate : null,
            'recipient_email' => $recipient,
            'email_subject' => 'AADE receipt only',
            'notification_status' => 'skipped',
            'skip_reason' => 'main_notification_missing',
            'error_message' => null,
            'sent_at' => null,
            'receipt_subject' => $subject,
            'receipt_status' => $status,
            'receipt_skip_reason' => $status === 'sent' ? null : ($error ?? 'official_receipt_not_sent'),
            'receipt_error_message' => $status === 'sent' ? null : $error,
            'receipt_sent_at' => $sentAt,
            'receipt_vat_rate' => (string)($aadeReceipt['vat_rate'] ?? $this->receiptVatPercent()),
            'receipt_total_amount' => number_format($totals['gross'], 2, '.', ''),
            'receipt_net_amount' => number_format($totals['net'], 2, '.', ''),
            'receipt_vat_amount' => number_format($totals['vat'], 2, '.', ''),
        ]);
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

        $mode = $this->receiptPdfMode();
        if ($mode === 'aade_mydata') {
            // Legal-production receipt mode. Do not fall back to generated or
            // static PDFs: a receipt must only be emailed after AADE/myDATA
            // issuance succeeds and the official MARK/UID/QR values are stored.
            return ['status' => 'skipped', 'reason' => 'aade_mydata_receipt_not_issued', 'error' => null];
        }

        if ($mode === 'generated') {
            try {
                $pdfBytes = $this->buildGeneratedReceiptPdf($intakeId, $row);
                $pdfName = $this->generatedReceiptPdfFilename($intakeId, $row);
                return $this->sendReceiptEmailWithPdfBytes($recipient, $subject, $intakeId, $row, $fromEmail, $fromName, $pdfBytes, $pdfName, 'bolt-mail-driver-generated-receipt-pdf');
            } catch (Throwable $e) {
                if ($this->receiptPdfAttachmentRequired()) {
                    return ['status' => 'failed', 'reason' => null, 'error' => 'generated_receipt_pdf_failed: ' . $e->getMessage()];
                }
            }
        }

        if ($this->receiptPdfAttachmentEnabled()) {
            $pdfPath = $this->receiptPdfAttachmentPath();
            if ($pdfPath === '' || !is_file($pdfPath) || !is_readable($pdfPath)) {
                if ($this->receiptPdfAttachmentRequired()) {
                    return ['status' => 'skipped', 'reason' => 'receipt_pdf_attachment_missing', 'error' => null];
                }
            } else {
                return $this->sendReceiptEmailWithPdfAttachment($recipient, $subject, $intakeId, $row, $fromEmail, $fromName, $pdfPath);
            }
        }

        // Backward-compatible fallback only. Prefer receipt_pdf_mode=generated or
        // an official PDF attachment. This HTML body is not the receipt document.
        $body = $this->buildReceiptHtmlBody($intakeId, $row);
        $encodedBody = chunk_split(base64_encode($body), 76, "\r\n");
        $headers = $this->buildHeaders($fromEmail, $fromName, 'text/html', 'bolt-mail-driver-receipt-html-fallback', 'base64');

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

    /**
     * @return array{status:string,reason:?string,error:?string}
     */
    private function sendReceiptEmailWithPdfAttachment(
        string $recipient,
        string $subject,
        int $intakeId,
        array $row,
        string $fromEmail,
        string $fromName,
        string $pdfPath
    ): array {
        $pdfBytes = @file_get_contents($pdfPath);
        if ($pdfBytes === false || $pdfBytes === '') {
            return ['status' => 'failed', 'reason' => null, 'error' => 'receipt_pdf_attachment_unreadable'];
        }

        $pdfName = $this->safeAttachmentFilename((string)($this->config['receipt_pdf_attachment_filename'] ?? 'lux-limo-receipt.pdf'));
        return $this->sendReceiptEmailWithPdfBytes($recipient, $subject, $intakeId, $row, $fromEmail, $fromName, $pdfBytes, $pdfName, 'bolt-mail-driver-official-receipt-pdf');
    }

    /**
     * @return array{status:string,reason:?string,error:?string}
     */
    private function sendReceiptEmailWithPdfBytes(
        string $recipient,
        string $subject,
        int $intakeId,
        array $row,
        string $fromEmail,
        string $fromName,
        string $pdfBytes,
        string $pdfName,
        string $bridgeHeader
    ): array {
        if ($pdfBytes === '') {
            return ['status' => 'failed', 'reason' => null, 'error' => 'receipt_pdf_attachment_empty'];
        }

        $boundary = 'cabnet_receipt_' . bin2hex(random_bytes(12));
        $htmlBody = $this->buildReceiptAttachmentHtmlBody($intakeId, $row);
        $pdfName = $this->safeAttachmentFilename($pdfName);

        $message = '';
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody), 76, "\r\n") . "\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: application/pdf; name="' . $pdfName . '"' . "\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . $pdfName . '"' . "\r\n\r\n";
        $message .= chunk_split(base64_encode($pdfBytes), 76, "\r\n") . "\r\n";
        $message .= '--' . $boundary . "--\r\n";

        $headers = $this->buildMultipartHeaders($fromEmail, $fromName, $boundary, $bridgeHeader);

        try {
            $sent = @mail($recipient, $this->encodeHeader($subject), $message, implode("\r\n", $headers), '-f' . $fromEmail);
        } catch (Throwable $e) {
            return ['status' => 'failed', 'reason' => null, 'error' => $e->getMessage()];
        }

        if (!$sent) {
            return ['status' => 'failed', 'reason' => null, 'error' => 'php_mail_returned_false'];
        }

        return ['status' => 'sent', 'reason' => null, 'error' => null];
    }

    private function buildReceiptAttachmentHtmlBody(int $intakeId, array $row): string
    {
        $logoUrl = trim((string)($this->config['receipt_logo_url'] ?? 'https://gov.cabnet.app/assets/logos/lux-limo-logo.jpeg'));
        $customerName = $this->value($row, 'customer_name');
        $driverName = $this->value($row, 'driver_name');
        $vehiclePlate = $this->value($row, 'vehicle_plate');
        $pickup = $this->value($row, 'pickup_address');
        $dropoff = $this->value($row, 'dropoff_address');
        $pickupTime = $this->value($row, 'estimated_pickup_time_raw');
        $endTime = $this->driverCopyEstimatedEndTime($row);
        $price = $this->driverCopyEstimatedPrice($row);

        $logoHtml = '';
        if ($logoUrl !== '') {
            $logoHtml = '<img src="' . $this->eh($logoUrl) . '" alt="LUX LIMO" style="display:block;margin:0 auto 12px auto;width:190px;max-width:80%;height:auto;border:0;outline:none;text-decoration:none;">';
        }

        $rows = [
            'Passenger' => $customerName,
            'Driver' => $driverName,
            'Vehicle' => $vehiclePlate,
            'Pickup' => $pickup,
            'Drop-off' => $dropoff,
            'Pick-up time' => $pickupTime,
            'End time' => $endTime,
            'Price' => $price,
        ];

        $details = '';
        foreach ($rows as $label => $value) {
            $details .= '<tr>'
                . '<td style="padding:9px 12px;border-bottom:1px solid #e6eef5;color:#64748b;font-size:13px;font-weight:bold;width:34%;vertical-align:top;">' . $this->eh($label) . '</td>'
                . '<td style="padding:9px 12px;border-bottom:1px solid #e6eef5;color:#0f172a;font-size:14px;vertical-align:top;">' . $this->eh($value) . '</td>'
                . '</tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Official receipt attached</title></head>'
            . '<body style="margin:0;padding:0;background:#eef4f8;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef4f8;width:100%;border-collapse:collapse;"><tr><td align="center" style="padding:22px 12px;">'
            . '<table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:680px;max-width:100%;background:#ffffff;border:1px solid #cfe0eb;border-radius:16px;overflow:hidden;border-collapse:separate;">'
            . '<tr><td style="padding:26px 26px 18px 26px;text-align:center;background:#ffffff;">'
            . $logoHtml
            . '<div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#0ea5c6;font-weight:bold;">LUX LIMO MYKONOS</div>'
            . '<h1 style="margin:8px 0 4px 0;font-size:24px;line-height:1.25;color:#0f172a;font-weight:700;">Official receipt PDF attached</h1>'
            . '<div style="font-size:13px;color:#64748b;line-height:1.45;">The attached PDF is the receipt document and preserves the official layout, QR/code area, tax fields, and company marking.</div>'
            . '</td></tr>'
            . '<tr><td style="height:5px;background:#0ea5c6;line-height:5px;font-size:1px;">&nbsp;</td></tr>'
            . '<tr><td style="padding:22px 26px 8px 26px;">'
            . '<div style="background:#f8fcff;border:1px solid #d7e9f3;border-radius:14px;overflow:hidden;">'
            . '<div style="background:#0f172a;color:#ffffff;padding:14px 16px;font-size:17px;font-weight:bold;">Transfer details</div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">' . $details . '</table>'
            . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:14px 26px 24px 26px;">'
            . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:13px 14px;font-size:13px;color:#334155;line-height:1.55;">'
            . '<strong>Bridge intake ID:</strong> #' . $this->eh((string)$intakeId) . '<br>'
            . '<strong>Safety:</strong> this email only sends the receipt PDF attachment. No EDXEIX submission was performed by this notification.'
            . '</div>'
            . '<div style="text-align:center;font-size:11px;color:#94a3b8;line-height:1.45;margin-top:14px;">LUX LIMO I.K.E. / MYKONOS CAB · Mykonos, Greece<br>Phone / WhatsApp: (+30) 694 654 0444</div>'
            . '</td></tr></table>'
            . '</td></tr></table>'
            . '</body></html>';
    }

    private function receiptPdfMode(): string
    {
        $mode = strtolower(trim((string)($this->config['receipt_pdf_mode'] ?? 'generated')));
        return in_array($mode, ['generated', 'static', 'official', 'html', 'aade_mydata'], true) ? $mode : 'generated';
    }

    /** @param array<string,mixed> $row */
    private function generatedReceiptPdfFilename(int $intakeId, array $row): string
    {
        $prefix = trim((string)($this->config['generated_receipt_pdf_filename_prefix'] ?? 'lux-limo-transfer-receipt'));
        if ($prefix === '') {
            $prefix = 'lux-limo-transfer-receipt';
        }
        $date = 'ride';
        $pickupRaw = trim((string)($row['estimated_pickup_time_raw'] ?? $row['parsed_pickup_at'] ?? ''));
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $pickupRaw, $m)) {
            $date = $m[1] . $m[2] . $m[3];
        }
        return $this->safeAttachmentFilename($prefix . '-' . $date . '-intake-' . $intakeId . '.pdf');
    }

    /** @param array<string,mixed> $row */
    private function buildGeneratedReceiptPdf(int $intakeId, array $row): string
    {
        $totals = $this->receiptTotals($row);
        $currency = (string)($totals['currency'] ?? 'EUR');
        $gross = $totals['gross'] !== null ? (float)$totals['gross'] : 0.0;
        $net = $totals['net'] !== null ? (float)$totals['net'] : 0.0;
        $vat = $totals['vat'] !== null ? (float)$totals['vat'] : 0.0;
        $vatPercent = $this->receiptVatPercent();

        $receiptNo = 'BR-' . date('Ymd') . '-' . str_pad((string)$intakeId, 6, '0', STR_PAD_LEFT);
        $payloadHash = hash('sha256', implode('|', [
            (string)$intakeId,
            (string)($row['message_hash'] ?? ''),
            (string)($row['driver_name'] ?? ''),
            (string)($row['vehicle_plate'] ?? ''),
            (string)($row['estimated_pickup_time_raw'] ?? $row['parsed_pickup_at'] ?? ''),
            number_format($gross, 2, '.', ''),
        ]));
        $verifyPayload = 'CABNET-RECEIPT|' . $receiptNo . '|TOTAL=' . number_format($gross, 2, '.', '') . '|HASH=' . substr($payloadHash, 0, 16);

        $pdf = new InternalReceiptPdfBuilder('LUX LIMO / MYKONOS CAB - Transfer Receipt');
        $pdf->addPage();

        $logoPath = $this->localAssetPath((string)($this->config['receipt_logo_path'] ?? '/home/cabnet/public_html/gov.cabnet.app/assets/logos/lux-limo-logo.jpeg'));
        if ($logoPath !== '') {
            $pdf->imageJpeg($logoPath, 382, 735, 145, 0);
        }

        $stampPath = $this->localAssetPath((string)($this->config['receipt_stamp_path'] ?? '/home/cabnet/public_html/gov.cabnet.app/assets/stamps/lux-limo-stamp.jpg'));
        if ($stampPath !== '') {
            $pdf->imageJpeg($stampPath, 365, 98, 135, 0);
        }

        $pdf->setFont('Helvetica', 'B', 18);
        $pdf->text(48, 760, 'LUX LIMO I.K.E.');
        $pdf->setFont('Helvetica', '', 9);
        $pdf->text(48, 744, 'AFM/VAT: 802653254');
        $pdf->text(48, 731, 'Tourist Office - Mykonos 84600, Greece');
        $pdf->text(48, 718, 'Phone / WhatsApp: (+30) 694 654 0444');

        $pdf->setFont('Helvetica', 'B', 15);
        $pdf->text(48, 682, 'TRANSFER RECEIPT');
        $pdf->setFont('Helvetica', '', 9);
        $pdf->text(48, 666, 'Bridge-generated receipt/pro-forma from Bolt pre-ride data.');
        $pdf->text(48, 653, 'Not an official AADE/myDATA receipt unless issued separately.');

        $pdf->roundedRect(380, 645, 150, 50, 6, [238, 246, 250], [190, 210, 220]);
        $pdf->setFont('Helvetica', 'B', 9);
        $pdf->text(392, 675, 'Receipt reference');
        $pdf->setFont('Helvetica', 'B', 11);
        $pdf->text(392, 658, $receiptNo);

        $y = 615;
        $pdf->sectionTitle(48, $y, 'Ride details');
        $y -= 24;
        $details = [
            ['Operator', (string)($row['operator_raw'] ?? 'Fleet Mykonos LUXLIMO I.K.E.||MYKONOS CAB')],
            ['Passenger', $this->value($row, 'customer_name')],
            ['Customer mobile', $this->value($row, 'customer_mobile')],
            ['Driver', $this->value($row, 'driver_name')],
            ['Vehicle', $this->value($row, 'vehicle_plate')],
            ['Pickup', $this->value($row, 'pickup_address')],
            ['Drop-off', $this->value($row, 'dropoff_address')],
            ['Pick-up time', $this->value($row, 'estimated_pickup_time_raw')],
            ['End time', $this->driverCopyEstimatedEndTime($row)],
            ['Price', $this->driverCopyEstimatedPrice($row)],
        ];
        foreach ($details as [$label, $value]) {
            $pdf->tableRow(48, $y, 500, (string)$label, $this->pdfSafeText((string)$value));
            $y -= 24;
        }

        $y -= 10;
        $pdf->sectionTitle(48, $y, 'VAT / TAX included in total');
        $y -= 26;
        $pdf->moneyRow(48, $y, 500, 'Net amount before VAT', $this->formatMoney($net, $currency));
        $y -= 24;
        $pdf->moneyRow(48, $y, 500, 'VAT / TAX included (' . number_format($vatPercent, 2, '.', '') . '%)', $this->formatMoney($vat, $currency));
        $y -= 24;
        $pdf->moneyRow(48, $y, 500, 'Total, VAT included', $this->formatMoney($gross, $currency), true);

        $pdf->setFont('Helvetica', 'B', 9);
        $pdf->text(48, 192, 'Bridge verification QR');
        $pdf->drawQrCode($verifyPayload, 48, 78, 96);
        $pdf->setFont('Helvetica', '', 7);
        $pdf->text(152, 172, 'Verification payload:');
        $pdf->textWrap(152, 160, 380, 10, $verifyPayload, 7);
        $pdf->text(152, 124, 'Payload hash: ' . substr($payloadHash, 0, 32));
        $pdf->text(152, 112, 'Bridge intake ID: #' . $intakeId);

        $pdf->setFont('Helvetica', '', 7);
        $pdf->text(48, 42, 'Generated by gov.cabnet.app. No EDXEIX submission is performed by this receipt email.');

        return $pdf->output();
    }

    private function localAssetPath(string $path): string
    {
        $path = trim($path);
        if ($path !== '' && is_file($path) && is_readable($path)) {
            return $path;
        }
        return '';
    }

    private function pdfSafeText(string $value): string
    {
        $map = [
            'Ι' => 'I', 'Κ' => 'K', 'Ε' => 'E', 'Μ' => 'M', 'Ο' => 'O', 'Ν' => 'N', 'Π' => 'P', 'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
            'ι' => 'i', 'κ' => 'k', 'ε' => 'e', 'μ' => 'm', 'ο' => 'o', 'ν' => 'n', 'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'ς' => 's', 'τ' => 't', 'υ' => 'y', 'χ' => 'x',
            'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Λ' => 'L', 'Φ' => 'F', 'Ω' => 'O', 'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'λ' => 'l', 'φ' => 'f', 'ω' => 'o',
            '→' => '->', '–' => '-', '—' => '-', '€' => 'EUR', '’' => "'", '“' => '"', '”' => '"',
        ];
        $value = strtr($value, $map);
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function receiptPdfAttachmentEnabled(): bool
    {
        return filter_var($this->config['receipt_pdf_attachment_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    private function receiptPdfAttachmentRequired(): bool
    {
        return filter_var($this->config['receipt_pdf_attachment_required'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    private function receiptPdfAttachmentPath(): string
    {
        $path = trim((string)($this->config['receipt_pdf_attachment_path'] ?? ''));
        if ($path !== '') {
            return $path;
        }

        return '/home/cabnet/gov.cabnet.app_app/storage/receipt_attachments/lux_limo_official_receipt_attachment.pdf';
    }

    private function safeAttachmentFilename(string $filename): string
    {
        $filename = trim(str_replace(["\r", "\n", '"', "'"], '', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? $filename;
        $filename = trim($filename, '.-_');
        return $filename !== '' ? $filename : 'lux-limo-receipt.pdf';
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

    /**
     * @return array<int,string>
     */
    private function buildMultipartHeaders(string $fromEmail, string $fromName, string $boundary, string $bridgeHeader): array
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
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $this->stripHeaderUnsafe($boundary) . '"';
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

final class InternalReceiptPdfBuilder
{
    private string $title;
    private string $content = '';
    /** @var array<int,array{name:string,data:string,width:int,height:int}> */
    private array $images = [];
    private int $imageCounter = 0;
    private string $font = 'F1';
    private float $fontSize = 10.0;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function addPage(): void
    {
        $this->content = '';
    }

    public function setFont(string $family, string $style, float $size): void
    {
        $this->font = strtoupper($style) === 'B' ? 'F2' : 'F1';
        $this->fontSize = $size;
    }

    public function text(float $x, float $y, string $text): void
    {
        $this->content .= sprintf("BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n", $this->font, $this->fontSize, $x, $y, $this->esc($text));
    }

    public function textWrap(float $x, float $y, float $width, float $lineHeight, string $text, float $fontSize = 8): void
    {
        $this->setFont('Helvetica', '', $fontSize);
        $max = max(18, (int)floor($width / max(3.8, $fontSize * 0.52)));
        $lines = str_split($text, $max);
        foreach ($lines as $i => $line) {
            $this->text($x, $y - ($i * $lineHeight), $line);
        }
    }

    /** @param array<int,int> $fill @param array<int,int> $stroke */
    public function roundedRect(float $x, float $y, float $w, float $h, float $r, array $fill, array $stroke): void
    {
        $this->setFill($fill);
        $this->setStroke($stroke);
        $this->content .= sprintf("%.2F %.2F %.2F %.2F re B\n", $x, $y, $w, $h);
        $this->setFill([0, 0, 0]);
    }

    public function sectionTitle(float $x, float $y, string $title): void
    {
        $this->setFill([14, 165, 198]);
        $this->content .= sprintf("%.2F %.2F %.2F %.2F re f\n", $x, $y - 5, 500.0, 17.0);
        $this->setFill([255, 255, 255]);
        $this->setFont('Helvetica', 'B', 9);
        $this->text($x + 8, $y, $title);
        $this->setFill([0, 0, 0]);
    }

    public function tableRow(float $x, float $y, float $w, string $label, string $value): void
    {
        $this->setStroke([226, 232, 240]);
        $this->setFill([248, 252, 255]);
        $this->content .= sprintf("%.2F %.2F %.2F %.2F re B\n", $x, $y - 8, $w, 22.0);
        $this->setFont('Helvetica', 'B', 8);
        $this->setFill([70, 86, 105]);
        $this->text($x + 8, $y, $label);
        $this->setFont('Helvetica', '', 8);
        $this->setFill([15, 23, 42]);
        $this->text($x + 145, $y, $this->trimTo($value, 72));
    }

    public function moneyRow(float $x, float $y, float $w, string $label, string $amount, bool $strong = false): void
    {
        $this->setStroke([203, 213, 225]);
        $this->setFill($strong ? [239, 253, 255] : [255, 255, 255]);
        $this->content .= sprintf("%.2F %.2F %.2F %.2F re B\n", $x, $y - 8, $w, 22.0);
        $this->setFont('Helvetica', $strong ? 'B' : '', $strong ? 10 : 9);
        $this->setFill([15, 23, 42]);
        $this->text($x + 8, $y, $label);
        $this->setFont('Helvetica', 'B', $strong ? 11 : 9);
        $this->text($x + $w - 120, $y, $amount);
    }

    public function imageJpeg(string $path, float $x, float $y, float $w, float $h = 0): void
    {
        $info = @getimagesize($path);
        if (!is_array($info) || (int)$info[0] < 1 || (int)$info[1] < 1) {
            return;
        }
        $data = @file_get_contents($path);
        if ($data === false || $data === '') {
            return;
        }
        $iw = (int)$info[0];
        $ih = (int)$info[1];
        if ($h <= 0) {
            $h = $w * ($ih / $iw);
        }
        $name = 'Im' . (++$this->imageCounter);
        $this->images[] = ['name' => $name, 'data' => $data, 'width' => $iw, 'height' => $ih];
        $this->content .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $w, $h, $x, $y, $name);
    }

    public function drawQrCode(string $payload, float $x, float $y, float $size): void
    {
        $matrix = InternalReceiptQrCode::matrix($payload);
        $count = count($matrix);
        if ($count < 1) {
            return;
        }
        $cell = $size / $count;
        $this->setFill([255, 255, 255]);
        $this->content .= sprintf("%.2F %.2F %.2F %.2F re f\n", $x - 4, $y - 4, $size + 8, $size + 8);
        $this->setFill([0, 0, 0]);
        for ($row = 0; $row < $count; $row++) {
            for ($col = 0; $col < $count; $col++) {
                if (!empty($matrix[$row][$col])) {
                    $this->content .= sprintf("%.2F %.2F %.3F %.3F re f\n", $x + $col * $cell, $y + ($count - 1 - $row) * $cell, $cell + 0.02, $cell + 0.02);
                }
            }
        }
    }

    public function output(): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';

        $imageObjectNumbers = [];
        $nextObj = 6;
        foreach ($this->images as $img) {
            $imageObjectNumbers[$img['name']] = $nextObj++;
        }
        $contentObj = $nextObj;

        $xObjects = '';
        foreach ($imageObjectNumbers as $name => $objNo) {
            $xObjects .= '/' . $name . ' ' . $objNo . ' 0 R ';
        }
        $resources = '<< /Font << /F1 4 0 R /F2 5 0 R >>' . ($xObjects !== '' ? ' /XObject << ' . trim($xObjects) . ' >>' : '') . ' >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources ' . $resources . ' /Contents ' . $contentObj . ' 0 R >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        foreach ($this->images as $img) {
            $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . $img['width'] . ' /Height ' . $img['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($img['data']) . " >>\nstream\n" . $img['data'] . "\nendstream";
        }

        $objects[] = '<< /Length ' . strlen($this->content) . " >>\nstream\n" . $this->content . "endstream";

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R /Info << /Title (" . $this->esc($this->title) . ") >> >>\n";
        $pdf .= "startxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }

    /** @param array<int,int> $rgb */
    private function setFill(array $rgb): void
    {
        $this->content .= sprintf("%.3F %.3F %.3F rg\n", ($rgb[0] ?? 0) / 255, ($rgb[1] ?? 0) / 255, ($rgb[2] ?? 0) / 255);
    }

    /** @param array<int,int> $rgb */
    private function setStroke(array $rgb): void
    {
        $this->content .= sprintf("%.3F %.3F %.3F RG\n", ($rgb[0] ?? 0) / 255, ($rgb[1] ?? 0) / 255, ($rgb[2] ?? 0) / 255);
    }

    private function esc(string $text): string
    {
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text) ?? $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function trimTo(string $text, int $max): string
    {
        return strlen($text) > $max ? substr($text, 0, $max - 3) . '...' : $text;
    }
}

final class InternalReceiptQrCode
{
    /** @return array<int,array<int,bool>> */
    public static function matrix(string $payload): array
    {
        $version = 5;
        $size = 37;
        $dataCodewords = 108;
        $eccCodewords = 26;
        $payload = substr($payload, 0, 100);

        $bits = [];
        self::appendBits($bits, 0x4, 4);
        self::appendBits($bits, strlen($payload), 8);
        foreach (array_values(unpack('C*', $payload) ?: []) as $b) {
            self::appendBits($bits, (int)$b, 8);
        }
        $capacityBits = $dataCodewords * 8;
        self::appendBits($bits, 0, min(4, $capacityBits - count($bits)));
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $data = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | (int)$bits[$i + $j];
            }
            $data[] = $byte;
        }
        for ($pad = 0; count($data) < $dataCodewords; $pad ^= 1) {
            $data[] = $pad === 0 ? 0xEC : 0x11;
        }
        $ecc = self::reedSolomonRemainder($data, $eccCodewords);
        $allCodewords = array_merge($data, $ecc);

        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $isFunc = array_fill(0, $size, array_fill(0, $size, false));
        $setFunc = static function (int $x, int $y, bool $dark) use (&$modules, &$isFunc, $size): void {
            if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                $modules[$y][$x] = $dark;
                $isFunc[$y][$x] = true;
            }
        };

        $drawFinder = static function (int $cx, int $cy) use ($setFunc): void {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $dist = max(abs($dx), abs($dy));
                    $setFunc($cx + $dx, $cy + $dy, $dist !== 2 && $dist !== 4);
                }
            }
        };
        $drawFinder(3, 3);
        $drawFinder($size - 4, 3);
        $drawFinder(3, $size - 4);

        for ($i = 0; $i < $size; $i++) {
            if (!$isFunc[6][$i]) {
                $setFunc($i, 6, $i % 2 === 0);
            }
            if (!$isFunc[$i][6]) {
                $setFunc(6, $i, $i % 2 === 0);
            }
        }

        $center = 30;
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $setFunc($center + $dx, $center + $dy, $dist !== 1);
            }
        }

        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $setFunc(8, $i, false);
                $setFunc($i, 8, false);
            }
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $setFunc(8, $i, false);
            $setFunc($i, 8, false);
        }
        $setFunc(8, $size - 8, true);

        $dataBits = [];
        foreach ($allCodewords as $cw) {
            for ($i = 7; $i >= 0; $i--) {
                $dataBits[] = (($cw >> $i) & 1) !== 0;
            }
        }

        $bitIndex = 0;
        $upward = true;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                $y = $upward ? $size - 1 - $vert : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if ($isFunc[$y][$x]) {
                        continue;
                    }
                    $dark = $bitIndex < count($dataBits) ? $dataBits[$bitIndex++] : false;
                    if ((($x + $y) & 1) === 0) {
                        $dark = !$dark;
                    }
                    $modules[$y][$x] = $dark;
                }
            }
            $upward = !$upward;
        }

        $format = self::formatBits(1, 0); // ECL L, mask 0
        $bit = static fn(int $i): bool => (($format >> $i) & 1) !== 0;
        for ($i = 0; $i <= 5; $i++) $setFunc(8, $i, $bit($i));
        $setFunc(8, 7, $bit(6));
        $setFunc(8, 8, $bit(7));
        $setFunc(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) $setFunc(14 - $i, 8, $bit($i));
        for ($i = 0; $i < 8; $i++) $setFunc($size - 1 - $i, 8, $bit($i));
        for ($i = 8; $i < 15; $i++) $setFunc(8, $size - 15 + $i, $bit($i));
        $setFunc(8, $size - 8, true);

        return $modules;
    }

    /** @param array<int,int> $bits */
    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /** @param array<int,int> $data @return array<int,int> */
    private static function reedSolomonRemainder(array $data, int $degree): array
    {
        $divisor = self::reedSolomonDivisor($degree);
        $result = array_fill(0, $degree, 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= self::gfMultiply($divisor[$i], $factor);
            }
        }
        return $result;
    }

    /** @return array<int,int> */
    private static function reedSolomonDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 2);
        }
        return $result;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            if ((($y >> $i) & 1) !== 0) {
                $z ^= $x;
            }
        }
        return $z;
    }

    private static function formatBits(int $ecl, int $mask): int
    {
        $data = ($ecl << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        return (($data << 10) | ($rem & 0x3FF)) ^ 0x5412;
    }
}
