<?php

namespace Bridge\Mail;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class BoltPreRideEmailParser
{
    private DateTimeZone $timezone;

    public function __construct(?DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?? new DateTimeZone('Europe/Athens');
    }

    /**
     * Parse a Bolt pre-ride email from a raw Maildir file.
     *
     * This parser intentionally stores normalized fields only. It does not
     * expose or persist the full email body.
     */
    public function parse(string $rawEmail, string $sourcePath = ''): array
    {
        $headers = $this->parseHeaders($rawEmail);
        $text = $this->extractReadableText($rawEmail);
        $fields = $this->extractFields($text);

        $start = $this->parseLocalDateTime($fields['start_time_raw'] ?? '');
        $pickup = $this->parseLocalDateTime($fields['estimated_pickup_time_raw'] ?? '');
        $end = $this->parseLocalDateTime($fields['estimated_end_time_raw'] ?? '');

        $sender = $this->extractEmailAddress((string)($headers['from'] ?? ''));
        $originalSender = $this->extractForwardedSender($text);
        if ($originalSender !== '') {
            $sender = $originalSender;
        }

        $messageId = trim((string)($headers['message-id'] ?? ''));
        if ($messageId === '') {
            $messageId = null;
        }

        $subject = $this->decodeHeaderValue((string)($headers['subject'] ?? ''));
        $receivedAt = $this->parseHeaderDate((string)($headers['date'] ?? ''));

        $timezoneLabel = $pickup['timezone_label'] ?? $start['timezone_label'] ?? $end['timezone_label'] ?? null;

        return [
            'source_path' => $sourcePath,
            'source_basename' => $sourcePath !== '' ? basename($sourcePath) : null,
            'message_hash' => hash('sha256', $rawEmail),
            'message_id' => $messageId,
            'subject' => $subject !== '' ? $subject : null,
            'sender_email' => $sender !== '' ? $sender : null,
            'received_at' => $receivedAt,
            'operator_raw' => $this->nullIfBlank($fields['operator_raw'] ?? null),
            'customer_name' => $this->nullIfBlank($fields['customer_name'] ?? null),
            'customer_mobile' => $this->normalizeMobile($fields['customer_mobile'] ?? null),
            'driver_name' => $this->nullIfBlank($fields['driver_name'] ?? null),
            'vehicle_plate' => $this->normalizePlate($fields['vehicle_plate'] ?? null),
            'pickup_address' => $this->nullIfBlank($fields['pickup_address'] ?? null),
            'dropoff_address' => $this->nullIfBlank($fields['dropoff_address'] ?? null),
            'start_time_raw' => $this->nullIfBlank($fields['start_time_raw'] ?? null),
            'estimated_pickup_time_raw' => $this->nullIfBlank($fields['estimated_pickup_time_raw'] ?? null),
            'estimated_end_time_raw' => $this->nullIfBlank($fields['estimated_end_time_raw'] ?? null),
            'estimated_price_raw' => $this->nullIfBlank($fields['estimated_price_raw'] ?? null),
            'parsed_start_at' => $start['mysql'] ?? null,
            'parsed_pickup_at' => $pickup['mysql'] ?? null,
            'parsed_end_at' => $end['mysql'] ?? null,
            'timezone_label' => $timezoneLabel,
            'raw_text_has_bolt_markers' => $this->hasBoltMarkers($text),
        ];
    }

    private function parseHeaders(string $rawEmail): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawEmail);
        $parts = explode("\n\n", $normalized, 2);
        $headerText = $parts[0] ?? '';
        $headerText = preg_replace("/\n[\t ]+/", ' ', $headerText) ?? $headerText;
        $headers = [];

        foreach (explode("\n", $headerText) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    private function extractReadableText(string $rawEmail): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawEmail);
        [$headerText, $body] = array_pad(explode("\n\n", $normalized, 2), 2, '');
        $topHeaders = $this->headersFromText($headerText);
        $contentType = strtolower((string)($topHeaders['content-type'] ?? ''));

        $parts = [];
        $boundary = $this->extractBoundary($contentType);
        if ($boundary !== '') {
            foreach ($this->splitMimeParts($body, $boundary) as $part) {
                $partText = $this->decodeMimePart($part);
                if ($partText !== '') {
                    $parts[] = $partText;
                }
            }
        }

        if (empty($parts)) {
            $parts[] = $this->decodeBodyByHeaders($body, $topHeaders);
        }

        $text = implode("\n", $parts);
        return $this->cleanText($text);
    }

    private function headersFromText(string $headerText): array
    {
        $headerText = preg_replace("/\n[\t ]+/", ' ', $headerText) ?? $headerText;
        $headers = [];
        foreach (explode("\n", $headerText) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
        return $headers;
    }

    private function extractBoundary(string $contentType): string
    {
        if (preg_match('/boundary=("([^"]+)"|([^;\s]+))/i', $contentType, $m)) {
            return $m[2] !== '' ? $m[2] : $m[3];
        }
        return '';
    }

    private function splitMimeParts(string $body, string $boundary): array
    {
        $chunks = preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?\s*\n/', $body) ?: [];
        $parts = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || $chunk === '--') {
                continue;
            }
            $parts[] = $chunk;
        }
        return $parts;
    }

    private function decodeMimePart(string $part): string
    {
        [$headerText, $body] = array_pad(explode("\n\n", $part, 2), 2, '');
        $headers = $this->headersFromText($headerText);
        $contentType = strtolower((string)($headers['content-type'] ?? ''));

        $nestedBoundary = $this->extractBoundary($contentType);
        if ($nestedBoundary !== '') {
            $text = [];
            foreach ($this->splitMimeParts($body, $nestedBoundary) as $nested) {
                $nestedText = $this->decodeMimePart($nested);
                if ($nestedText !== '') {
                    $text[] = $nestedText;
                }
            }
            return implode("\n", $text);
        }

        if ($contentType !== '' && !str_contains($contentType, 'text/plain') && !str_contains($contentType, 'text/html')) {
            return '';
        }

        return $this->decodeBodyByHeaders($body, $headers);
    }

    private function decodeBodyByHeaders(string $body, array $headers): string
    {
        $encoding = strtolower((string)($headers['content-transfer-encoding'] ?? ''));
        $decoded = $body;

        if ($encoding === 'base64') {
            $decodedBody = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true);
            if (is_string($decodedBody)) {
                $decoded = $decodedBody;
            }
        } elseif ($encoding === 'quoted-printable') {
            $decoded = quoted_printable_decode($body);
        } else {
            $decoded = quoted_printable_decode($body);
        }

        return $decoded;
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\s*\/\s*(p|div|tr|li|h[1-6])\s*>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xc2\xa0", "\xE2\x80\xAF"], ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function extractFields(string $text): array
    {
        $fields = [
            'operator_raw' => $this->extractLabelValue($text, 'Operator'),
            'customer_mobile' => $this->extractLabelValue($text, 'Customer mobile'),
            'customer_name' => $this->extractLabelValue($text, 'Customer'),
            'driver_name' => $this->extractLabelValue($text, 'Driver'),
            'vehicle_plate' => $this->extractLabelValue($text, 'Vehicle'),
            'pickup_address' => $this->extractLabelValue($text, 'Pickup'),
            'dropoff_address' => $this->extractLabelValue($text, 'Drop-off'),
            'start_time_raw' => $this->extractLabelValue($text, 'Start time'),
            'estimated_pickup_time_raw' => $this->extractLabelValue($text, 'Estimated pick-up time'),
            'estimated_end_time_raw' => $this->extractLabelValue($text, 'Estimated end time'),
            'estimated_price_raw' => $this->extractLabelValue($text, 'Estimated price'),
        ];

        return $fields;
    }

    private function extractLabelValue(string $text, string $label): ?string
    {
        $labelPattern = preg_quote($label, '/');
        $labelPattern = str_replace('pick\-up', 'pick[-‑–]up', $labelPattern);
        $labelPattern = str_replace('Drop\-off', 'Drop[-‑–]off', $labelPattern);

        if (preg_match('/^\s*' . $labelPattern . '\s*:\s*(.*?)\s*$/imu', $text, $m)) {
            return trim($m[1]);
        }

        // Fallback for HTML/plain text that collapses labels onto fewer lines.
        $nextLabels = 'Operator|Customer mobile|Customer|Driver|Vehicle|Pickup|Drop[-‑–]off|Start time|Estimated pick[-‑–]up time|Estimated end time|Estimated price';
        if (preg_match('/' . $labelPattern . '\s*:\s*(.*?)(?=\s+(?:' . $nextLabels . ')\s*:|\z)/isu', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function parseLocalDateTime(?string $value): ?array
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})(?:\s+([A-Z]{2,5}))?/i', $value, $m)) {
            return null;
        }

        $local = $m[1] . ' ' . $m[2];
        $timezoneLabel = isset($m[3]) ? strtoupper($m[3]) : null;
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $local, $this->timezone);

        if (!$dt instanceof DateTimeImmutable) {
            return null;
        }

        return [
            'mysql' => $dt->format('Y-m-d H:i:s'),
            'timezone_label' => $timezoneLabel,
        ];
    }

    private function parseHeaderDate(string $dateHeader): ?string
    {
        $dateHeader = trim($this->decodeHeaderValue($dateHeader));
        if ($dateHeader === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($dateHeader))->setTimezone($this->timezone)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function extractForwardedSender(string $text): string
    {
        if (preg_match('/^\s*(?:From|Από)\s*:\s*.*?<([^>]+)>/imu', $text, $m)) {
            return strtolower(trim($m[1]));
        }
        if (preg_match('/\bgreece@bolt\.eu\b/i', $text, $m)) {
            return strtolower($m[0]);
        }
        return '';
    }

    private function extractEmailAddress(string $value): string
    {
        $value = $this->decodeHeaderValue($value);
        if (preg_match('/<([^>]+)>/', $value, $m)) {
            return strtolower(trim($m[1]));
        }
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $m)) {
            return strtolower($m[0]);
        }
        return '';
    }

    private function decodeHeaderValue(string $value): string
    {
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        return $value;
    }

    private function normalizeMobile(?string $value): ?string
    {
        $value = $this->nullIfBlank($value);
        if ($value === null) {
            return null;
        }
        return preg_replace('/\s+/', '', $value) ?: $value;
    }

    private function normalizePlate(?string $value): ?string
    {
        $value = $this->nullIfBlank($value);
        if ($value === null) {
            return null;
        }
        return strtoupper(preg_replace('/\s+/', '', $value) ?: $value);
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function hasBoltMarkers(string $text): bool
    {
        $required = ['Operator:', 'Customer:', 'Driver:', 'Vehicle:', 'Pickup:', 'Estimated pick-up time:'];
        foreach ($required as $marker) {
            if (stripos($text, $marker) === false) {
                return false;
            }
        }
        return true;
    }
}
