<?php
/**
 * gov.cabnet.app — Bolt pre-ride email parser
 *
 * Purpose:
 * - Extract transfer data from the Bolt pre-ride email body.
 * - Feed a manual, editable operations form.
 *
 * Safety:
 * - No database access.
 * - No network calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No storage/logging of pasted email content.
 */

declare(strict_types=1);

namespace Bridge\BoltMail;

final class BoltPreRideEmailParser
{
    private const MAX_INPUT_BYTES = 60000;

    /**
     * @return array<string,mixed>
     */
    public function parse(string $raw): array
    {
        $warnings = [];
        $raw = $this->limitInput($raw, $warnings);
        $normalized = $this->normalizeBody($raw);
        $labelMap = $this->extractLabelMap($normalized);

        $fields = [
            'operator' => $this->pick($labelMap, ['operator']),
            'customer_name' => $this->pick($labelMap, ['customer', 'client', 'passenger']),
            'customer_phone' => $this->normalizePhone($this->pick($labelMap, ['customer mobile', 'customer phone', 'customer telephone', 'mobile', 'phone', 'telephone'])),
            'driver_name' => $this->pick($labelMap, ['driver', 'driver name']),
            'vehicle_plate' => $this->normalizePlate($this->pick($labelMap, ['vehicle', 'vehicle plate', 'licence plate', 'license plate', 'plate'])),
            'pickup_address' => $this->pick($labelMap, ['pickup', 'pick up', 'pick-up', 'pickup address', 'from']),
            'dropoff_address' => $this->pick($labelMap, ['drop off', 'drop-off', 'dropoff', 'drop off address', 'drop-off address', 'to', 'destination']),
            'start_time_text' => $this->pick($labelMap, ['start time', 'ride start time']),
            'estimated_pickup_time_text' => $this->pick($labelMap, ['estimated pick up time', 'estimated pick-up time', 'estimated pickup time', 'pickup time']),
            'estimated_end_time_text' => $this->pick($labelMap, ['estimated end time', 'end time', 'estimated finish time', 'estimated drop off time', 'estimated drop-off time']),
            'estimated_price_text' => $this->pick($labelMap, ['estimated price', 'price', 'fare', 'estimated fare']),
            'order_reference' => $this->pick($labelMap, ['order id', 'order reference', 'order uuid', 'ride id', 'trip id', 'booking id']),
        ];

        $pickupDateTime = $this->parseLocalDateTime($fields['estimated_pickup_time_text'] ?: $fields['start_time_text']);
        $startDateTime = $this->parseLocalDateTime($fields['start_time_text']);
        $endDateTime = $this->parseLocalDateTime($fields['estimated_end_time_text']);

        $fields['pickup_date'] = $pickupDateTime['date'];
        $fields['pickup_time'] = $pickupDateTime['time'];
        $fields['pickup_datetime_local'] = $pickupDateTime['datetime_local'];
        $fields['pickup_timezone'] = $pickupDateTime['timezone'];

        $fields['start_date'] = $startDateTime['date'];
        $fields['start_time'] = $startDateTime['time'];
        $fields['start_datetime_local'] = $startDateTime['datetime_local'];
        $fields['start_timezone'] = $startDateTime['timezone'];

        $fields['end_date'] = $endDateTime['date'];
        $fields['end_time'] = $endDateTime['time'];
        $fields['end_datetime_local'] = $endDateTime['datetime_local'];
        $fields['end_timezone'] = $endDateTime['timezone'];

        $fields['estimated_price_amount'] = $this->extractPriceAmount($fields['estimated_price_text']);
        $fields['estimated_price_currency'] = $this->extractPriceCurrency($fields['estimated_price_text']);

        $missing = $this->missingRequired($fields);
        if ($fields['start_time_text'] !== '' && $fields['estimated_pickup_time_text'] === '') {
            $warnings[] = 'Estimated pick-up time was not found; the form uses Start time as the pickup datetime fallback.';
        }
        if ($fields['estimated_price_text'] === '') {
            $warnings[] = 'Estimated price was empty or not included in the email.';
        }

        return [
            'ok' => count($missing) === 0,
            'version' => 'v6.6.2-pre-ride-email-utility',
            'source' => 'pasted_bolt_pre_ride_email',
            'safety' => [
                'database_access' => false,
                'edxeix_call' => false,
                'aade_call' => false,
                'stores_email_body' => false,
            ],
            'raw_length' => strlen($raw),
            'detected_labels' => array_keys($labelMap),
            'fields' => $fields,
            'missing_required' => $missing,
            'warnings' => array_values(array_unique($warnings)),
            'confidence' => $this->confidence($missing, $warnings),
            'generated' => $this->generatedText($fields),
        ];
    }

    /**
     * @param array<int,string> $warnings
     */
    private function limitInput(string $raw, array &$warnings): string
    {
        if (strlen($raw) <= self::MAX_INPUT_BYTES) {
            return $raw;
        }

        $warnings[] = 'Input was longer than the safety limit and was truncated before parsing.';
        return substr($raw, 0, self::MAX_INPUT_BYTES);
    }

    private function normalizeBody(string $raw): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $raw);

        if (preg_match('/=[0-9A-F]{2}/i', $body) === 1) {
            $body = quoted_printable_decode($body);
        }

        if (stripos($body, '<br') !== false || stripos($body, '<html') !== false || stripos($body, '<div') !== false) {
            $body = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $body) ?? $body;
            $body = preg_replace('/<\s*\/\s*p\s*>/i', "\n", $body) ?? $body;
            $body = preg_replace('/<\s*\/\s*div\s*>/i', "\n", $body) ?? $body;
            $body = strip_tags($body);
        }

        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = str_replace("\xC2\xA0", ' ', $body);
        $body = preg_replace('/[\t ]+/', ' ', $body) ?? $body;
        $body = preg_replace('/\n{3,}/', "\n\n", $body) ?? $body;

        return trim($body);
    }

    /**
     * @return array<string,string>
     */
    private function extractLabelMap(string $body): array
    {
        $lines = preg_split('/\n/', $body) ?: [];
        $map = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = trim((string)$lines[$i]);
            if ($line === '') {
                continue;
            }

            if (!preg_match('/^([\p{L}0-9][\p{L}0-9 ._\-\/()]*?)\s*:\s*(.*)$/u', $line, $m)) {
                continue;
            }

            $key = $this->normalizeKey($m[1]);
            $value = trim($m[2]);

            if ($value === '') {
                $next = $this->nextNonEmptyLine($lines, $i + 1);
                if ($next !== '' && !preg_match('/^[\p{L}0-9][\p{L}0-9 ._\-\/()]*?\s*:/u', $next)) {
                    $value = $next;
                }
            }

            if ($key !== '' && !isset($map[$key])) {
                $map[$key] = $this->cleanValue($value);
            }
        }

        return $map;
    }

    /**
     * @param array<int,string> $lines
     */
    private function nextNonEmptyLine(array $lines, int $start): string
    {
        $count = count($lines);
        for ($i = $start; $i < $count; $i++) {
            $line = trim((string)$lines[$i]);
            if ($line !== '') {
                return $line;
            }
        }
        return '';
    }

    private function normalizeKey(string $key): string
    {
        $key = function_exists('mb_strtolower') ? mb_strtolower(trim($key), 'UTF-8') : strtolower(trim($key));
        $key = str_replace(['–', '—', '_'], '-', $key);
        $key = str_replace('-', ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;
        return trim($key);
    }

    private function cleanValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    }

    /**
     * @param array<string,string> $map
     * @param array<int,string> $keys
     */
    private function pick(array $map, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeKey($key);
            if (isset($map[$normalized]) && $map[$normalized] !== '') {
                return $map[$normalized];
            }
        }
        return '';
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        $phone = str_replace([' ', '-', '(', ')'], '', $phone);
        return $phone;
    }

    private function normalizePlate(string $plate): string
    {
        $plate = trim($plate);
        if ($plate === '') {
            return '';
        }
        $plate = preg_replace('/\s+/', '', $plate) ?? $plate;
        return function_exists('mb_strtoupper') ? mb_strtoupper($plate, 'UTF-8') : strtoupper($plate);
    }

    /**
     * @return array{date:string,time:string,datetime_local:string,timezone:string}
     */
    private function parseLocalDateTime(string $text): array
    {
        $empty = ['date' => '', 'time' => '', 'datetime_local' => '', 'timezone' => ''];
        $text = trim($text);
        if ($text === '') {
            return $empty;
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})\s+(\d{1,2}:\d{2}(?::\d{2})?)\s*([A-Z]{2,5})?/i', $text, $m) !== 1) {
            return $empty;
        }

        $date = $m[1];
        $time = $m[2];
        $timezone = isset($m[3]) ? strtoupper((string)$m[3]) : '';

        if (preg_match('/^\d{1,2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }

        return [
            'date' => $date,
            'time' => $time,
            'datetime_local' => $date . ' ' . $time,
            'timezone' => $timezone,
        ];
    }

    private function extractPriceAmount(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (preg_match('/([0-9]+(?:[,.][0-9]{1,2})?)/', $text, $m) !== 1) {
            return '';
        }
        return str_replace(',', '.', $m[1]);
    }

    private function extractPriceCurrency(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (str_contains($text, '€') || preg_match('/\bEUR\b/i', $text) === 1) {
            return 'EUR';
        }
        return '';
    }

    /**
     * @param array<string,string> $fields
     * @return array<int,string>
     */
    private function missingRequired(array $fields): array
    {
        $required = [
            'customer_name' => 'Customer',
            'customer_phone' => 'Customer mobile',
            'driver_name' => 'Driver',
            'vehicle_plate' => 'Vehicle',
            'pickup_address' => 'Pickup',
            'dropoff_address' => 'Drop-off',
            'pickup_datetime_local' => 'Pickup datetime',
            'estimated_end_time_text' => 'Estimated end time',
        ];

        $missing = [];
        foreach ($required as $key => $label) {
            if (!isset($fields[$key]) || trim((string)$fields[$key]) === '') {
                $missing[] = $label;
            }
        }
        return $missing;
    }

    /**
     * @param array<int,string> $missing
     * @param array<int,string> $warnings
     */
    private function confidence(array $missing, array $warnings): string
    {
        if (count($missing) === 0 && count($warnings) <= 1) {
            return 'high';
        }
        if (count($missing) <= 2) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * @param array<string,string> $fields
     * @return array<string,string>
     */
    private function generatedText(array $fields): array
    {
        $summaryLines = [
            'Customer: ' . ($fields['customer_name'] ?: '-'),
            'Mobile: ' . ($fields['customer_phone'] ?: '-'),
            'Pickup: ' . ($fields['pickup_address'] ?: '-'),
            'Drop-off: ' . ($fields['dropoff_address'] ?: '-'),
            'Pickup time: ' . (($fields['pickup_datetime_local'] ?? '') ?: '-'),
            'Estimated end: ' . (($fields['end_datetime_local'] ?? '') ?: ($fields['estimated_end_time_text'] ?: '-')),
            'Driver: ' . ($fields['driver_name'] ?: '-'),
            'Vehicle: ' . ($fields['vehicle_plate'] ?: '-'),
            'Estimated price: ' . ($fields['estimated_price_text'] ?: '-'),
        ];

        $csv = [
            $fields['pickup_date'] ?? '',
            $fields['pickup_time'] ?? '',
            $fields['customer_name'] ?? '',
            $fields['customer_phone'] ?? '',
            $fields['pickup_address'] ?? '',
            $fields['dropoff_address'] ?? '',
            $fields['driver_name'] ?? '',
            $fields['vehicle_plate'] ?? '',
            $fields['estimated_price_amount'] ?? '',
            $fields['estimated_price_currency'] ?? '',
            $fields['order_reference'] ?? '',
        ];

        return [
            'dispatch_summary' => implode("\n", $summaryLines),
            'csv_header' => 'pickup_date,pickup_time,customer_name,customer_phone,pickup_address,dropoff_address,driver_name,vehicle_plate,estimated_price_amount,estimated_price_currency,order_reference',
            'csv_row' => $this->csvLine($csv),
        ];
    }

    /**
     * @param array<int,string> $cells
     */
    private function csvLine(array $cells): string
    {
        $escaped = [];
        foreach ($cells as $cell) {
            $cell = (string)$cell;
            $escaped[] = '"' . str_replace('"', '""', $cell) . '"';
        }
        return implode(',', $escaped);
    }
}
