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
        $grossCents = (int)round($gross * 100);
        $net = round($gross / (1 + ($vatRatePercent / 100)), 2);
        $netCents = (int)round($net * 100);
        $vatCents = $grossCents - $netCents;
        $vat = $vatCents / 100;

        $issueDate = (new DateTimeImmutable('now'))->format('Y-m-d');
        $bookingId = (int)($booking['id'] ?? 0);
        $intakeId = isset($intake['id']) ? (int)$intake['id'] : null;
        $orderReference = trim((string)($booking['order_reference'] ?? $booking['source_trip_id'] ?? ''));

        $series = trim((string)($aade['series'] ?? 'BOLT'));
        /*
         * v6.1.4:
         * AADE/myDATA requires invoiceHeader/aa to be numeric for Greek issuers.
         * Keep the human-readable series as BOLT, but use numeric AA only.
         */
        $aa = trim((string)($aade['aa'] ?? ''));
        if ($aa === '') {
            $aa = $bookingId > 0
                ? (string)$bookingId
                : (string)abs((int)crc32($orderReference));
        }
        $aa = preg_replace('/\D+/', '', $aa) ?: ($bookingId > 0 ? (string)$bookingId : '1');

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

        $customerName = $this->effectiveCustomerName($booking, $intake);

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
            'gross_amount' => $this->fmt($gross),
            'net_amount' => $this->fmt($net),
            'vat_amount' => $this->fmt($vat),
            'vat_rate_percent' => $this->fmtRate($vatRatePercent),
            'gross_amount_cents' => $grossCents,
            'net_amount_cents' => $netCents,
            'vat_amount_cents' => $vatCents,
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
        $gross = (float)($summary['gross_amount'] ?? 0);
        $net = (float)($summary['net_amount'] ?? 0);
        $vat = (float)($summary['vat_amount'] ?? 0);
        if (abs(($net + $vat) - $gross) > 0.01) {
            $blockers[] = 'net_vat_total_mismatch';
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
        if (!$this->config->get('receipts.aade_mydata.allow_send_invoices', false)) {
            $warnings[] = 'send_invoices_disabled_in_config';
        }
        if ($this->config->get('mail.driver_notifications.receipt_copy_enabled', false)) {
            $warnings[] = 'driver_receipt_copy_enabled_before_aade_issue_flow';
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
        $this->elIncomeClassification($doc, $class, 'classificationType', (string)$summary['income_classification_type']);
        $this->elIncomeClassification($doc, $class, 'classificationCategory', (string)$summary['income_classification_category']);
        $this->elIncomeClassification($doc, $class, 'amount', $this->fmt((float)$summary['net_amount']));

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
        $this->elIncomeClassification($doc, $class2, 'classificationType', (string)$summary['income_classification_type']);
        $this->elIncomeClassification($doc, $class2, 'classificationCategory', (string)$summary['income_classification_category']);
        $this->elIncomeClassification($doc, $class2, 'amount', $this->fmt((float)$summary['net_amount']));

        return (string)$doc->saveXML();
    }

    private function lineComment(array $summary, string $description): string
    {
        /*
         * v6.1.2:
         * AADE/myDATA lineComments has a strict max length.
         * Do not include full pickup/dropoff addresses here.
         * Keep the official receipt comment short and stable.
         */
        $bookingId = trim((string)($summary['booking_id'] ?? ''));
        $customer = trim((string)($summary['customer_name'] ?? ''));
        $driver = trim((string)($summary['driver_name'] ?? ''));
        $plate = trim((string)($summary['vehicle_plate'] ?? ''));

        $parts = [];
        $parts[] = trim($description) !== '' ? trim($description) : 'Transfer services';

        if ($bookingId !== '') {
            $parts[] = 'Booking ' . $bookingId;
        }
        if ($customer !== '' && !$this->isGenericCustomerName($customer)) {
            $parts[] = 'Passenger ' . $customer;
        }
        if ($driver !== '') {
            $parts[] = 'Driver ' . $driver;
        }
        if ($plate !== '') {
            $parts[] = 'Vehicle ' . $plate;
        }

        $comment = implode(' | ', $parts);
        $comment = preg_replace('/\s+/u', ' ', $comment) ?: 'Transfer services';

        $max = (int)$this->config->get('receipts.aade_mydata.line_comments_max_length', 100);
        if ($max < 40 || $max > 120) {
            $max = 100;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($comment, 0, $max, 'UTF-8');
        }

        return substr($comment, 0, $max);
    }


    /**
     * Resolve the passenger/customer name for receipts.
     *
     * Bolt API bookings often contain empty customer_name plus the placeholder
     * "Bolt Passenger" in passenger_name/lessee_name. The matched pre-ride
     * email intake is the authoritative source for the real passenger name.
     *
     * @param array<string,mixed> $booking
     * @param array<string,mixed> $intake
     */
    private function effectiveCustomerName(array $booking, array $intake): string
    {
        $customerName = $this->firstRealCustomerName([
            $intake['customer_name'] ?? null,
            $booking['customer_name'] ?? null,
            $booking['passenger_name'] ?? null,
            $booking['lessee_name'] ?? null,
        ]);

        return $customerName !== '' ? $customerName : 'ΠΕΛΑΤΗΣ ΛΙΑΝΙΚΗΣ';
    }

    /** @param array<int,mixed> $values */
    private function firstRealCustomerName(array $values): string
    {
        foreach ($values as $value) {
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }
            $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?: '';
            if ($text === '' || $this->isGenericCustomerName($text)) {
                continue;
            }
            return $text;
        }

        return '';
    }

    private function isGenericCustomerName(string $value): bool
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';
        $upper = function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);

        return in_array($upper, [
            'BOLT PASSENGER',
            'BOLT CUSTOMER',
            'ΠΕΛΑΤΗΣ ΛΙΑΝΙΚΗΣ',
            'ΠΕΛΑΤΗΣ',
            'CUSTOMER',
            'PASSENGER',
        ], true);
    }

    private function elIncomeClassification(DOMDocument $doc, \DOMNode $parent, string $name, string $value): void
    {
        /*
         * v6.1.3:
         * AADE/myDATA incomeClassification child elements must use the
         * incomeClassificaton namespace. The spelling below intentionally matches
         * the AADE production schema namespace shown in validation errors.
         */
        $parent->appendChild($doc->createElementNS(
            'https://www.aade.gr/myDATA/incomeClassificaton/v1.0',
            'icls:' . $name,
            $value
        ));
    }

    private function el(DOMDocument $doc, \DOMNode $parent, string $name, string $value): void
    {
        $parent->appendChild($doc->createElement($name, $value));
    }

    private function fmt(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    private function fmtRate(float $amount): string
    {
        $formatted = number_format(round($amount, 2), 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
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
