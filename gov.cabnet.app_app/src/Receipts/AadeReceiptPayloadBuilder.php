<?php

declare(strict_types=1);

namespace Bridge\Receipts;

use Bridge\Config;
use Bridge\Database;
use DateTimeImmutable;
use DOMDocument;
use RuntimeException;

final class AadeReceiptPayloadBuilder
{
    private Config $config;
    private Database $db;

    public function __construct(Config $config, Database $db)
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
     * @return array<string,mixed>
     */
    public function buildForBookingId(int $bookingId): array
    {
        if ($bookingId <= 0) {
            throw new RuntimeException('booking_id must be positive.');
        }

        $booking = $this->db->fetchOne('SELECT * FROM normalized_bookings WHERE id=? LIMIT 1', [$bookingId], 'i');
        if (!is_array($booking)) {
            throw new RuntimeException('normalized_bookings row not found.');
        }

        $intake = $this->db->fetchOne('SELECT * FROM bolt_mail_intake WHERE linked_booking_id=? ORDER BY id DESC LIMIT 1', [$bookingId], 'i');
        if (!is_array($intake)) {
            $intake = [];
        }

        return $this->build($booking, $intake);
    }

    /**
     * @param array<string,mixed> $booking
     * @param array<string,mixed> $intake
     * @return array<string,mixed>
     */
    public function build(array $booking, array $intake = []): array
    {
        $aade = $this->aadeConfig();
        $vatRatePercent = (float)($this->config->get('receipts.vat_rate_percent', $aade['vat_category_percent'] ?? 13));
        if ($vatRatePercent <= 0) {
            $vatRatePercent = 13.0;
        }

        $gross = $this->money($booking['price'] ?? 0);
        $net = round($gross / (1 + ($vatRatePercent / 100)), 2);
        $vat = round($gross - $net, 2);

        $issueDate = (new DateTimeImmutable('now'))->format('Y-m-d');
        $bookingId = (int)($booking['id'] ?? 0);
        $intakeId = isset($intake['id']) ? (int)$intake['id'] : null;
        $orderReference = trim((string)($booking['order_reference'] ?? $booking['source_trip_id'] ?? ''));

        $series = trim((string)($aade['series'] ?? 'BOLT'));
        $aaPrefix = trim((string)($aade['aa_prefix'] ?? 'BOLT-'));
        $aa = trim((string)($aade['aa'] ?? ''));
        if ($aa === '') {
            $aa = $aaPrefix . ($bookingId > 0 ? (string)$bookingId : substr(hash('sha256', $orderReference), 0, 10));
        }

        $invoiceType = trim((string)($aade['invoice_type'] ?? '11.2'));
        $currency = trim((string)($booking['currency'] ?? 'EUR')) ?: 'EUR';
        $vatCategory = (int)($aade['vat_category'] ?? $this->vatCategoryForPercent($vatRatePercent));
        $paymentType = (int)($aade['payment_method_type'] ?? 1);
        $classificationType = trim((string)($aade['income_classification_type'] ?? 'E3_561_003'));
        $classificationCategory = trim((string)($aade['income_classification_category'] ?? 'category1_3'));
        $description = trim((string)($aade['line_description'] ?? 'Transfer services'));

        $issuerVat = preg_replace('/\D+/', '', (string)($aade['issuer_vat_number'] ?? ''));
        $issuerCountry = strtoupper(trim((string)($aade['issuer_country'] ?? 'GR'))) ?: 'GR';
        $issuerBranch = (int)($aade['issuer_branch'] ?? $aade['branch'] ?? 0);

        $customerName = trim((string)($booking['customer_name'] ?? $booking['passenger_name'] ?? $intake['customer_name'] ?? 'ΠΕΛΑΤΗΣ ΛΙΑΝΙΚΗΣ'));
        if ($customerName === '') {
            $customerName = 'ΠΕΛΑΤΗΣ ΛΙΑΝΙΚΗΣ';
        }

        $summary = [
            'booking_id' => $bookingId,
            'intake_id' => $intakeId,
            'source' => (string)($booking['source'] ?? ''),
            'source_system' => (string)($booking['source_system'] ?? ''),
            'order_reference' => $orderReference,
            'issuer_vat_number' => $issuerVat,
            'issuer_name' => (string)($aade['issuer_name'] ?? ''),
            'document_type' => $invoiceType,
            'series' => $series,
            'aa' => $aa,
            'issue_date' => $issueDate,
            'currency' => $currency,
            'customer_name' => $customerName,
            'driver_name' => (string)($booking['driver_name'] ?? $intake['driver_name'] ?? ''),
            'vehicle_plate' => (string)($booking['vehicle_plate'] ?? $intake['vehicle_plate'] ?? ''),
            'boarding_point' => (string)($booking['boarding_point'] ?? $booking['pickup_address'] ?? $intake['pickup_address'] ?? ''),
            'disembark_point' => (string)($booking['disembark_point'] ?? $booking['destination_address'] ?? $intake['dropoff_address'] ?? ''),
            'started_at' => (string)($booking['started_at'] ?? $intake['parsed_pickup_at'] ?? ''),
            'gross_amount' => $gross,
            'net_amount' => $net,
            'vat_amount' => $vat,
            'vat_rate_percent' => $vatRatePercent,
            'vat_category' => $vatCategory,
            'payment_method_type' => $paymentType,
            'income_classification_type' => $classificationType,
            'income_classification_category' => $classificationCategory,
        ];

        $validation = $this->validate($summary, $booking, $aade);
        $xml = $this->buildXml($summary, $description, $issuerCountry, $issuerBranch);

        return [
            'summary' => $summary,
            'validation' => $validation,
            'xml' => $xml,
            'xml_sha256' => hash('sha256', $xml),
            'xml_bytes' => strlen($xml),
        ];
    }

    /** @return array<string,mixed> */
    private function aadeConfig(): array
    {
        $cfg = $this->config->get('receipts.aade_mydata', []);
        return is_array($cfg) ? $cfg : [];
    }

    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $booking
     * @param array<string,mixed> $aade
     * @return array<string,mixed>
     */
    private function validate(array $summary, array $booking, array $aade): array
    {
        $blockers = [];
        $warnings = [];

        if (($this->config->get('receipts.mode') ?? '') !== 'aade_mydata') {
            $blockers[] = 'receipts_mode_not_aade_mydata';
        }
        if (empty($aade['enabled'])) {
            $blockers[] = 'aade_mydata_not_enabled';
        }
        if (trim((string)($aade['user_id'] ?? '')) === '' || trim((string)($aade['subscription_key'] ?? '')) === '') {
            $blockers[] = 'aade_credentials_missing';
        }
        if ((string)($summary['issuer_vat_number'] ?? '') === '') {
            $blockers[] = 'issuer_vat_missing';
        }
        if ((float)($summary['gross_amount'] ?? 0) <= 0) {
            $blockers[] = 'gross_amount_not_positive';
        }
        if ((string)($summary['document_type'] ?? '') === '') {
            $blockers[] = 'document_type_missing';
        }
        if ((string)($summary['series'] ?? '') === '' || (string)($summary['aa'] ?? '') === '') {
            $blockers[] = 'series_or_aa_missing';
        }
        if ((string)($summary['source'] ?? '') !== 'bolt_mail') {
            $warnings[] = 'source_is_not_bolt_mail';
        }
        if (trim((string)($summary['customer_name'] ?? '')) === '') {
            $warnings[] = 'customer_name_missing';
        }
        if (trim((string)($summary['driver_name'] ?? '')) === '') {
            $warnings[] = 'driver_name_missing';
        }

        $issued = 0;
        try {
            $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM receipt_issuance_attempts WHERE normalized_booking_id=? AND provider='aade_mydata' AND provider_status='issued'", [(int)($summary['booking_id'] ?? 0)], 'i');
            $issued = (int)($row['c'] ?? 0);
        } catch (\Throwable) {
            $warnings[] = 'issued_duplicate_check_unavailable';
        }
        if ($issued > 0) {
            $blockers[] = 'already_issued_for_booking';
        }

        return [
            'ok_for_preview' => empty($blockers),
            'ok_for_send_if_confirmed' => empty($blockers),
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    /** @param array<string,mixed> $summary */
    private function buildXml(array $summary, string $description, string $issuerCountry, int $issuerBranch): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS('http://www.aade.gr/myDATA/invoice/v1.0', 'InvoicesDoc');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $doc->appendChild($root);

        $invoice = $root->appendChild($doc->createElement('invoice'));

        $issuer = $invoice->appendChild($doc->createElement('issuer'));
        $this->el($doc, $issuer, 'vatNumber', (string)$summary['issuer_vat_number']);
        $this->el($doc, $issuer, 'country', $issuerCountry);
        $this->el($doc, $issuer, 'branch', (string)$issuerBranch);

        // Retail receipt. Counterpart is intentionally omitted by default, matching
        // common retail ΑΠΥ usage. If an accountant later requires counterpart fields,
        // add them in a dedicated patch/config section.

        $header = $invoice->appendChild($doc->createElement('invoiceHeader'));
        $this->el($doc, $header, 'series', (string)$summary['series']);
        $this->el($doc, $header, 'aa', (string)$summary['aa']);
        $this->el($doc, $header, 'issueDate', (string)$summary['issue_date']);
        $this->el($doc, $header, 'invoiceType', (string)$summary['document_type']);
        $this->el($doc, $header, 'vatPaymentSuspension', 'false');
        $this->el($doc, $header, 'currency', (string)$summary['currency']);

        $payments = $invoice->appendChild($doc->createElement('paymentMethods'));
        $payment = $payments->appendChild($doc->createElement('paymentMethodDetails'));
        $this->el($doc, $payment, 'type', (string)$summary['payment_method_type']);
        $this->el($doc, $payment, 'amount', $this->fmt((float)$summary['gross_amount']));

        $details = $invoice->appendChild($doc->createElement('invoiceDetails'));
        $this->el($doc, $details, 'lineNumber', '1');
        $this->el($doc, $details, 'netValue', $this->fmt((float)$summary['net_amount']));
        $this->el($doc, $details, 'vatCategory', (string)$summary['vat_category']);
        $this->el($doc, $details, 'vatAmount', $this->fmt((float)$summary['vat_amount']));
        $this->el($doc, $details, 'lineComments', $this->lineComment($summary, $description));

        $class = $details->appendChild($doc->createElement('incomeClassification'));
        $this->el($doc, $class, 'classificationType', (string)$summary['income_classification_type']);
        $this->el($doc, $class, 'classificationCategory', (string)$summary['income_classification_category']);
        $this->el($doc, $class, 'amount', $this->fmt((float)$summary['net_amount']));

        $summaryNode = $invoice->appendChild($doc->createElement('invoiceSummary'));
        $this->el($doc, $summaryNode, 'totalNetValue', $this->fmt((float)$summary['net_amount']));
        $this->el($doc, $summaryNode, 'totalVatAmount', $this->fmt((float)$summary['vat_amount']));
        $this->el($doc, $summaryNode, 'totalWithheldAmount', '0.00');
        $this->el($doc, $summaryNode, 'totalFeesAmount', '0.00');
        $this->el($doc, $summaryNode, 'totalStampDutyAmount', '0.00');
        $this->el($doc, $summaryNode, 'totalOtherTaxesAmount', '0.00');
        $this->el($doc, $summaryNode, 'totalDeductionsAmount', '0.00');
        $this->el($doc, $summaryNode, 'totalGrossValue', $this->fmt((float)$summary['gross_amount']));

        $class2 = $summaryNode->appendChild($doc->createElement('incomeClassification'));
        $this->el($doc, $class2, 'classificationType', (string)$summary['income_classification_type']);
        $this->el($doc, $class2, 'classificationCategory', (string)$summary['income_classification_category']);
        $this->el($doc, $class2, 'amount', $this->fmt((float)$summary['net_amount']));

        return (string)$doc->saveXML();
    }

    private function lineComment(array $summary, string $description): string
    {
        $parts = [$description];
        if (!empty($summary['order_reference'])) {
            $parts[] = 'Ref: ' . (string)$summary['order_reference'];
        }
        if (!empty($summary['driver_name'])) {
            $parts[] = 'Driver: ' . (string)$summary['driver_name'];
        }
        if (!empty($summary['vehicle_plate'])) {
            $parts[] = 'Vehicle: ' . (string)$summary['vehicle_plate'];
        }
        if (!empty($summary['boarding_point']) || !empty($summary['disembark_point'])) {
            $parts[] = 'Route: ' . trim((string)$summary['boarding_point']) . ' -> ' . trim((string)$summary['disembark_point']);
        }
        return mb_substr(implode(' | ', $parts), 0, 500, 'UTF-8');
    }

    private function el(DOMDocument $doc, \DOMNode $parent, string $name, string $value): void
    {
        $parent->appendChild($doc->createElement($name, $value));
    }

    private function fmt(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    private function money(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float)$value, 2);
        }
        $clean = preg_replace('/[^0-9.,-]+/', '', (string)$value) ?: '0';
        $clean = str_replace(',', '.', $clean);
        return round((float)$clean, 2);
    }

    private function vatCategoryForPercent(float $percent): int
    {
        $p = round($percent, 2);
        if (abs($p - 24.0) < 0.01) return 1;
        if (abs($p - 13.0) < 0.01) return 2;
        if (abs($p - 6.0) < 0.01) return 3;
        if (abs($p - 17.0) < 0.01) return 4;
        if (abs($p - 9.0) < 0.01) return 5;
        if (abs($p - 4.0) < 0.01) return 6;
        return 2;
    }
}
