<?php
/**
 * gov.cabnet.app — EDXEIX pre-ride future candidate diagnostic library v3.2.25
 *
 * Purpose:
 * - Convert a pasted/latest Bolt pre-ride email into a sanitized future EDXEIX candidate preview.
 * - Keep receipt-only Bolt mail rows blocked while allowing a separate pre-ride candidate path.
 * - Optionally capture candidate metadata into the additive edxeix_pre_ride_candidates table.
 * - v3.2.23 adds a diagnostics-only fallback label parser for Maildir bodies whose labels are not line-start normalized.
 * - v3.2.24 adds opt-in safe source diagnostics so empty Maildir parses can be inspected without exposing raw email bodies.
 * - v3.2.25 teaches the diagnostics-only fallback parser to clean <p>/<strong> HTML label rows before extraction.
 * - v3.2.26 fixes fallback multi-label detection when preg_match_all returns more than one match.
 *
 * Safety contract:
 * - Default mode is dry-run / analysis only.
 * - No EDXEIX HTTP transport.
 * - No AADE/myDATA calls.
 * - No submission jobs are created.
 * - No normalized_bookings rows are created or changed.
 * - Raw email body is never stored; only parsed fields, hashes, readiness, and payload preview may be stored when --write=1 is explicitly used.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

$__govPrcParser = '/home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php';
if (is_file($__govPrcParser)) { require_once $__govPrcParser; }
$__govPrcLookup = '/home/cabnet/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php';
if (is_file($__govPrcLookup)) { require_once $__govPrcLookup; }
$__govPrcLoader = '/home/cabnet/gov.cabnet.app_app/src/BoltMail/MaildirPreRideEmailLoader.php';
if (is_file($__govPrcLoader)) { require_once $__govPrcLoader; }

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\BoltMail\MaildirPreRideEmailLoader;

if (!function_exists('gov_prc_now')) {
    function gov_prc_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('gov_prc_bool')) {
    function gov_prc_bool($value): bool
    {
        if (is_bool($value)) { return $value; }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gov_prc_json')) {
    function gov_prc_json($value): string
    {
        if (function_exists('gov_bridge_json_encode_db')) {
            return gov_bridge_json_encode_db($value);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
    }
}

if (!function_exists('gov_prc_configured_future_guard_minutes')) {
    function gov_prc_configured_future_guard_minutes(): int
    {
        $config = gov_bridge_load_config();
        return (int)($config['edxeix']['future_start_guard_minutes'] ?? 30);
    }
}

if (!function_exists('gov_prc_effective_future_guard_minutes')) {
    function gov_prc_effective_future_guard_minutes(): int
    {
        return max(30, gov_prc_configured_future_guard_minutes());
    }
}

if (!function_exists('gov_prc_future_guard_passes')) {
    function gov_prc_future_guard_passes(?string $pickupAt, int $guardMinutes): bool
    {
        if (!$pickupAt) { return false; }
        $ts = strtotime($pickupAt);
        return $ts !== false && $ts >= (time() + ($guardMinutes * 60));
    }
}

if (!function_exists('gov_prc_normalize_plate')) {
    function gov_prc_normalize_plate(string $plate): string
    {
        $plate = preg_replace('/\s+/', '', trim($plate)) ?? trim($plate);
        return function_exists('mb_strtoupper') ? mb_strtoupper($plate, 'UTF-8') : strtoupper($plate);
    }
}

if (!function_exists('gov_prc_mapping_lookup')) {
    /**
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    function gov_prc_mapping_lookup(mysqli $db, array $fields): array
    {
        if (!class_exists(EdxeixMappingLookup::class)) {
            return [
                'ok' => false,
                'lessor_id' => '',
                'driver_id' => '',
                'vehicle_id' => '',
                'starting_point_id' => '',
                'messages' => [],
                'warnings' => ['EdxeixMappingLookup class is unavailable.'],
            ];
        }

        try {
            $lookup = new EdxeixMappingLookup($db);
            return $lookup->lookup($fields);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'lessor_id' => '',
                'driver_id' => '',
                'vehicle_id' => '',
                'starting_point_id' => '',
                'messages' => [],
                'warnings' => ['Mapping lookup failed: ' . $e->getMessage()],
            ];
        }
    }
}

if (!function_exists('gov_prc_admin_exclusion_status')) {
    /**
     * @return array{excluded:bool,reason:string,source:string,warnings:array<int,string>}
     */
    function gov_prc_admin_exclusion_status(mysqli $db, string $plate): array
    {
        $plateNorm = gov_prc_normalize_plate($plate);
        $warnings = [];

        if ($plateNorm === 'EMT8640') {
            return [
                'excluded' => true,
                'reason' => 'Mercedes-Benz Sprinter / EMT8640 is permanently Admin Excluded.',
                'source' => 'hard_safety_rule',
                'warnings' => [],
            ];
        }

        if ($plateNorm === '' || !function_exists('gov_bridge_table_exists') || !gov_bridge_table_exists($db, 'mapping_vehicles')) {
            return ['excluded' => false, 'reason' => '', 'source' => '', 'warnings' => $warnings];
        }

        try {
            $columns = gov_bridge_table_columns($db, 'mapping_vehicles');
            if (!isset($columns['plate'])) {
                return ['excluded' => false, 'reason' => '', 'source' => '', 'warnings' => $warnings];
            }

            $flagCols = [
                'admin_excluded', 'is_admin_excluded', 'exclude_from_edxeix', 'edxeix_excluded',
                'never_submit_live', 'no_edxeix', 'is_excluded', 'disabled_for_edxeix',
            ];
            $select = ['id', 'plate'];
            foreach ($flagCols as $col) {
                if (isset($columns[$col])) { $select[] = $col; }
            }
            foreach (['notes', 'admin_notes', 'exclusion_reason', 'block_reason'] as $col) {
                if (isset($columns[$col])) { $select[] = $col; }
            }

            $sql = 'SELECT ' . implode(', ', array_map('gov_bridge_quote_identifier', array_values(array_unique($select))))
                . ' FROM mapping_vehicles WHERE REPLACE(UPPER(plate), " ", "") = ? ORDER BY id DESC LIMIT 1';
            $row = gov_bridge_fetch_one($db, $sql, [$plateNorm]);
            if (!is_array($row)) {
                return ['excluded' => false, 'reason' => '', 'source' => '', 'warnings' => $warnings];
            }

            foreach ($flagCols as $col) {
                if (!array_key_exists($col, $row)) { continue; }
                $value = strtolower(trim((string)$row[$col]));
                if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                    return [
                        'excluded' => true,
                        'reason' => 'Vehicle mapping flag ' . $col . ' blocks automated EDXEIX processing.',
                        'source' => 'mapping_vehicles.' . $col,
                        'warnings' => $warnings,
                    ];
                }
            }

            foreach (['notes', 'admin_notes', 'exclusion_reason', 'block_reason'] as $col) {
                if (!array_key_exists($col, $row)) { continue; }
                $text = strtolower((string)$row[$col]);
                foreach (['admin excluded', 'no edxeix', 'exclude from edxeix', 'never submit', 'blocked from edxeix'] as $needle) {
                    if ($needle !== '' && strpos($text, $needle) !== false) {
                        return [
                            'excluded' => true,
                            'reason' => 'Vehicle mapping note blocks automated EDXEIX processing.',
                            'source' => 'mapping_vehicles.' . $col,
                            'warnings' => $warnings,
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            $warnings[] = 'Admin exclusion lookup warning: ' . $e->getMessage();
        }

        return ['excluded' => false, 'reason' => '', 'source' => '', 'warnings' => $warnings];
    }
}

if (!function_exists('gov_prc_extra_maildirs_from_config')) {
    /** @return array<int,string> */
    function gov_prc_extra_maildirs_from_config(): array
    {
        $config = gov_bridge_load_config();
        $dirs = [];
        foreach (['pre_ride_maildir', 'bolt_pre_ride_maildir'] as $key) {
            $value = $config['mail'][$key] ?? null;
            if (is_string($value) && trim($value) !== '') { $dirs[] = trim($value); }
        }
        foreach (['pre_ride_maildirs', 'bolt_pre_ride_maildirs'] as $key) {
            $many = $config['mail'][$key] ?? null;
            if (is_array($many)) {
                foreach ($many as $dir) {
                    if (is_string($dir) && trim($dir) !== '') { $dirs[] = trim($dir); }
                }
            }
        }
        return array_values(array_unique($dirs));
    }
}

if (!function_exists('gov_prc_source_from_options')) {
    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    function gov_prc_source_from_options(array $options): array
    {
        $warnings = [];
        $text = trim((string)($options['email_text'] ?? ''));
        $sourceLabel = 'pasted_text';
        $sourceType = 'pasted_bolt_pre_ride_email';
        $sourceMtime = '';
        $checkedDirs = [];

        if ($text === '') {
            $file = trim((string)($options['email_file'] ?? ''));
            if ($file !== '') {
                if (!is_file($file) || !is_readable($file)) {
                    $warnings[] = 'Email file is not readable: ' . $file;
                } else {
                    $raw = file_get_contents($file, false, null, 0, 120000);
                    $text = is_string($raw) ? trim($raw) : '';
                    $sourceLabel = basename($file);
                    $sourceType = 'file_bolt_pre_ride_email';
                    $sourceMtime = date('Y-m-d H:i:s', (int)@filemtime($file));
                }
            }
        }

        if ($text === '' && !empty($options['latest_mail'])) {
            if (!class_exists(MaildirPreRideEmailLoader::class)) {
                $warnings[] = 'MaildirPreRideEmailLoader class is unavailable.';
            } else {
                try {
                    $loader = new MaildirPreRideEmailLoader();
                    $loaded = $loader->loadLatest(gov_prc_extra_maildirs_from_config());
                    $checkedDirs = is_array($loaded['checked_dirs'] ?? null) ? $loaded['checked_dirs'] : [];
                    if (!empty($loaded['ok'])) {
                        $text = trim((string)($loaded['email_text'] ?? ''));
                        $sourceLabel = basename((string)($loaded['source'] ?? 'latest_maildir_message'));
                        $sourceType = 'latest_maildir_bolt_pre_ride_email';
                        $sourceMtime = (string)($loaded['source_mtime'] ?? '');
                    } else {
                        $warnings[] = 'Latest Maildir load failed: ' . (string)($loaded['error'] ?? 'unknown error');
                    }
                } catch (Throwable $e) {
                    $warnings[] = 'Latest Maildir load exception: ' . $e->getMessage();
                }
            }
        }

        return [
            'ok' => $text !== '',
            'email_text' => $text,
            'source_type' => $sourceType,
            'source_label' => $sourceLabel,
            'source_mtime' => $sourceMtime,
            'source_hash' => $text !== '' ? hash('sha256', $text) : '',
            'warnings' => $warnings,
            'checked_dirs' => $checkedDirs,
            'debug_source' => !empty($options['debug_source']),
            'debug_lines' => (int)($options['debug_lines'] ?? 24),
        ];
    }
}

if (!function_exists('gov_prc_build_payload_preview')) {
    /**
     * @param array<string,mixed> $fields
     * @param array<string,mixed> $mapping
     * @return array<string,mixed>
     */
    function gov_prc_build_payload_preview(array $fields, array $mapping): array
    {
        $config = gov_bridge_load_config();
        $broker = (string)($config['edxeix']['default_broker'] ?? '');
        $startingPoint = trim((string)($mapping['starting_point_id'] ?? ''));
        $customer = trim((string)($fields['customer_name'] ?? ''));
        $phone = trim((string)($fields['customer_phone'] ?? ''));
        $lessee = trim($customer . ($phone !== '' ? ' / ' . $phone : ''));

        return [
            'lessor' => trim((string)($mapping['lessor_id'] ?? '')),
            'broker' => $broker,
            'driver' => trim((string)($mapping['driver_id'] ?? '')),
            'vehicle' => trim((string)($mapping['vehicle_id'] ?? '')),
            'starting_point' => $startingPoint,
            'starting_point_id' => $startingPoint,
            'boarding_point' => trim((string)($fields['pickup_address'] ?? '')),
            'disembark_point' => trim((string)($fields['dropoff_address'] ?? '')),
            'lessee' => $lessee,
            'started_at' => trim((string)($fields['pickup_datetime_local'] ?? '')),
            'ended_at' => trim((string)($fields['end_datetime_local'] ?? '')),
            'price' => trim((string)($fields['estimated_price_amount'] ?? '')),
            'coordinates' => '',
            'drafted_at' => gov_prc_now(),
        ];
    }
}

if (!function_exists('gov_prc_payload_summary')) {
    function gov_prc_payload_summary(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);
        return [
            'field_count' => count($payload),
            'fields' => $keys,
            'payload_hash' => hash('sha256', gov_prc_json($payload)),
        ];
    }
}



if (!function_exists('gov_prc_debug_redact_line')) {
    function gov_prc_debug_redact_line(string $line): string
    {
        $line = trim($line);
        $line = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[EMAIL]', $line) ?? $line;
        $line = preg_replace('/\+?\d[\d\s().\-]{5,}\d/', '[PHONE_OR_ID]', $line) ?? $line;
        $line = preg_replace('/\b[A-Z0-9]{18,}\b/i', '[LONG_TOKEN]', $line) ?? $line;
        $line = preg_replace('/\s+/', ' ', $line) ?? $line;
        if (function_exists('mb_substr')) {
            return mb_strlen($line, 'UTF-8') > 180 ? mb_substr($line, 0, 180, 'UTF-8') . '…' : $line;
        }
        return strlen($line) > 180 ? substr($line, 0, 180) . '…' : $line;
    }
}

if (!function_exists('gov_prc_debug_structure_line')) {
    function gov_prc_debug_structure_line(string $line): string
    {
        $clean = gov_prc_debug_redact_line($line);
        if (preg_match('/^([^:]{1,80})\s*:\s*(.*)$/u', $clean, $m) === 1) {
            $label = trim((string)$m[1]);
            $value = trim((string)$m[2]);
            return $label . ': ' . ($value !== '' ? '[VALUE_REDACTED]' : '');
        }
        return $clean;
    }
}

if (!function_exists('gov_prc_source_debug_report')) {
    /** @return array<string,mixed> */
    function gov_prc_source_debug_report(string $text, int $maxLines = 24): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $text);
        $lower = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
        $lines = preg_split('/\n/', $raw) ?: [];
        $nonEmpty = [];
        foreach ($lines as $idx => $line) {
            $trim = trim((string)$line);
            if ($trim === '') { continue; }
            $nonEmpty[] = [
                'line' => $idx + 1,
                'text' => gov_prc_debug_structure_line($trim),
            ];
            if (count($nonEmpty) >= $maxLines) { break; }
        }

        $aliases = function_exists('gov_prc_fallback_label_aliases') ? gov_prc_fallback_label_aliases() : [];
        $phraseHits = [];
        $colonHits = [];
        foreach ($aliases as $field => $fieldAliases) {
            foreach ($fieldAliases as $alias) {
                $aliasLower = function_exists('mb_strtolower') ? mb_strtolower((string)$alias, 'UTF-8') : strtolower((string)$alias);
                if ($aliasLower !== '' && strpos($lower, $aliasLower) !== false) {
                    $phraseHits[$field][] = $alias;
                }
                if ($aliasLower !== '' && preg_match('/(?<![\p{L}0-9])' . preg_quote($aliasLower, '/') . '\s*:/iu', $lower) === 1) {
                    $colonHits[$field][] = $alias;
                }
            }
        }

        $printable = 0;
        $len = strlen($raw);
        if ($len > 0) {
            for ($i = 0; $i < $len; $i++) {
                $o = ord($raw[$i]);
                if ($o === 9 || $o === 10 || $o === 13 || ($o >= 32 && $o <= 126) || $o >= 128) { $printable++; }
            }
        }

        return [
            'enabled' => true,
            'safety' => 'Raw email body is not printed or stored; line values are redacted/truncated for structure diagnostics only.',
            'source_hash_16' => substr(hash('sha256', $text), 0, 16),
            'bytes' => strlen($text),
            'line_count' => count($lines),
            'non_empty_preview_count' => count($nonEmpty),
            'printable_ratio' => $len > 0 ? round($printable / $len, 4) : 0,
            'label_phrase_hit_fields' => array_keys($phraseHits),
            'label_colon_hit_fields' => array_keys($colonHits),
            'label_phrase_hits' => $phraseHits,
            'label_colon_hits' => $colonHits,
            'redacted_structure_lines' => $nonEmpty,
        ];
    }
}

if (!function_exists('gov_prc_fallback_normalize_key')) {
    function gov_prc_fallback_normalize_key(string $key): string
    {
        $key = function_exists('mb_strtolower') ? mb_strtolower(trim($key), 'UTF-8') : strtolower(trim($key));
        $key = str_replace(['–', '—', '_'], '-', $key);
        $key = str_replace('-', ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;
        return trim($key);
    }
}

if (!function_exists('gov_prc_fallback_clean_value')) {
    function gov_prc_fallback_clean_value(string $value): string
    {
        $value = trim($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/[\t ]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\n{2,}/', "\n", $value) ?? $value;
        $value = trim($value);
        // Keep the value compact for mapping/readiness; do not store raw mail blocks.
        if (strlen($value) > 600) {
            $value = substr($value, 0, 600);
        }
        return trim($value);
    }
}

if (!function_exists('gov_prc_fallback_label_aliases')) {
    /** @return array<string,array<int,string>> */
    function gov_prc_fallback_label_aliases(): array
    {
        return [
            'operator' => ['operator'],
            'customer_name' => ['customer name', 'customer', 'client name', 'client', 'passenger name', 'passenger', 'rider name'],
            'customer_phone' => ['customer mobile', 'customer phone', 'customer telephone', 'mobile phone', 'mobile', 'phone', 'telephone'],
            'driver_name' => ['driver name', 'driver'],
            'vehicle_plate' => ['vehicle plate', 'licence plate', 'license plate', 'vehicle', 'plate'],
            'pickup_address' => ['pickup address', 'pick up address', 'pick-up address', 'pickup', 'pick up', 'pick-up', 'from'],
            'dropoff_address' => ['drop off address', 'drop-off address', 'dropoff address', 'drop off', 'drop-off', 'dropoff', 'destination', 'to'],
            'start_time_text' => ['ride start time', 'start time'],
            'estimated_pickup_time_text' => ['estimated pick up time', 'estimated pick-up time', 'estimated pickup time', 'pickup time'],
            'estimated_end_time_text' => ['estimated end time', 'estimated finish time', 'estimated drop off time', 'estimated drop-off time', 'end time'],
            'estimated_price_text' => ['estimated price', 'estimated fare', 'price', 'fare'],
            'order_reference' => ['order reference', 'order uuid', 'order id', 'ride id', 'trip id', 'booking id'],
        ];
    }
}

if (!function_exists('gov_prc_fallback_extract_label_map')) {
    /** @return array<string,string> */
    function gov_prc_fallback_extract_label_map(string $text, ?array &$diagnostics = null): array
    {
        $diagnostics = [
            'fallback_label_hits' => 0,
            'fallback_labels' => [],
            'fallback_html_cleanup_applied' => false,
            'fallback_html_cleanup_reason' => '',
        ];

        $body = str_replace(["\r\n", "\r"], "\n", $text);
        $hasHtmlLabelRows = preg_match('/<\s*\/?\s*(br|html|body|div|p|strong|b|span|td|th|tr|table|section|img)\b/i', $body) === 1;
        if ($hasHtmlLabelRows) {
            $diagnostics['fallback_html_cleanup_applied'] = true;
            $diagnostics['fallback_html_cleanup_reason'] = 'html_label_rows_detected';
            $body = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $body) ?? $body;
            $body = preg_replace('/<\s*\/\s*(p|div|tr|table|section)\s*>/i', "\n", $body) ?? $body;
            $body = preg_replace('/<\s*\/\s*(td|th|span|strong|b)\s*>/i', ' ', $body) ?? $body;
            $body = strip_tags($body);
        }
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = str_replace("\xC2\xA0", ' ', $body);
        $body = preg_replace('/[\t ]+/', ' ', $body) ?? $body;

        $allLabels = [];
        foreach (gov_prc_fallback_label_aliases() as $aliases) {
            foreach ($aliases as $label) {
                $allLabels[] = $label;
            }
        }
        usort($allLabels, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $escaped = array_map(static fn(string $label): string => preg_quote($label, '/'), array_values(array_unique($allLabels)));
        $pattern = '/(?<![\p{L}0-9])(' . implode('|', $escaped) . ')\s*:\s*/iu';

        $matchResult = preg_match_all($pattern, $body, $matches, PREG_OFFSET_CAPTURE);
        if ($matchResult === false || $matchResult < 1) {
            $diagnostics['fallback_label_hits'] = 0;
            return [];
        }

        $map = [];
        $count = count($matches[0]);
        $diagnostics['fallback_label_hits'] = $count;
        for ($i = 0; $i < $count; $i++) {
            $label = gov_prc_fallback_normalize_key((string)$matches[1][$i][0]);
            $start = (int)$matches[0][$i][1] + strlen((string)$matches[0][$i][0]);
            $end = ($i + 1 < $count) ? (int)$matches[0][$i + 1][1] : strlen($body);
            if ($end <= $start) { continue; }
            $value = gov_prc_fallback_clean_value(substr($body, $start, $end - $start));
            if ($label !== '' && $value !== '' && !isset($map[$label])) {
                $map[$label] = $value;
                $diagnostics['fallback_labels'][] = $label;
            }
        }

        $diagnostics['fallback_labels'] = array_values(array_unique($diagnostics['fallback_labels']));
        return $map;
    }
}

if (!function_exists('gov_prc_fallback_pick')) {
    /** @param array<string,string> $map @param array<int,string> $aliases */
    function gov_prc_fallback_pick(array $map, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $key = gov_prc_fallback_normalize_key($alias);
            if (isset($map[$key]) && trim($map[$key]) !== '') {
                return trim($map[$key]);
            }
        }
        return '';
    }
}

if (!function_exists('gov_prc_fallback_parse_datetime')) {
    /** @return array{date:string,time:string,datetime_local:string,timezone:string} */
    function gov_prc_fallback_parse_datetime(string $text): array
    {
        $empty = ['date' => '', 'time' => '', 'datetime_local' => '', 'timezone' => ''];
        $text = trim($text);
        if ($text === '') { return $empty; }

        $date = '';
        $time = '';
        $timezone = '';
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})\D+(\d{1,2}:\d{2}(?::\d{2})?)\s*([A-Z]{2,5})?/i', $text, $m) === 1) {
            $date = $m[1] . '-' . $m[2] . '-' . $m[3];
            $time = $m[4];
            $timezone = isset($m[5]) ? strtoupper((string)$m[5]) : '';
        } elseif (preg_match('/(\d{1,2})[\/.](\d{1,2})[\/.](\d{4})\D+(\d{1,2}:\d{2}(?::\d{2})?)\s*([A-Z]{2,5})?/i', $text, $m) === 1) {
            $date = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            $time = $m[4];
            $timezone = isset($m[5]) ? strtoupper((string)$m[5]) : '';
        }

        if ($date === '' || $time === '') { return $empty; }
        if (preg_match('/^\d{1,2}:\d{2}$/', $time) === 1) { $time .= ':00'; }
        if (preg_match('/^(\d):/', $time) === 1) { $time = '0' . $time; }
        return ['date' => $date, 'time' => $time, 'datetime_local' => $date . ' ' . $time, 'timezone' => $timezone];
    }
}

if (!function_exists('gov_prc_fallback_price_amount')) {
    function gov_prc_fallback_price_amount(string $text): string
    {
        if (preg_match('/([0-9]+(?:[,.][0-9]{1,2})?)/', $text, $m) !== 1) { return ''; }
        $amount = str_replace(',', '.', $m[1]);
        return str_contains($amount, '.') ? rtrim(rtrim($amount, '0'), '.') : $amount;
    }
}

if (!function_exists('gov_prc_fallback_price_currency')) {
    function gov_prc_fallback_price_currency(string $text): string
    {
        return (str_contains($text, '€') || preg_match('/\bEUR\b/i', $text) === 1) ? 'EUR' : '';
    }
}

if (!function_exists('gov_prc_fallback_fields_from_text')) {
    /** @return array{fields:array<string,string>,missing_required:array<int,string>,diagnostics:array<string,mixed>} */
    function gov_prc_fallback_fields_from_text(string $text): array
    {
        $diagnostics = [];
        $map = gov_prc_fallback_extract_label_map($text, $diagnostics);
        $aliases = gov_prc_fallback_label_aliases();
        $fields = [];
        foreach ($aliases as $field => $fieldAliases) {
            $fields[$field] = gov_prc_fallback_pick($map, $fieldAliases);
        }

        $fields['customer_phone'] = str_replace([' ', '-', '(', ')'], '', (string)($fields['customer_phone'] ?? ''));
        $fields['vehicle_plate'] = gov_prc_normalize_plate((string)($fields['vehicle_plate'] ?? ''));

        $pickupDateTime = gov_prc_fallback_parse_datetime((string)(($fields['estimated_pickup_time_text'] ?? '') ?: ($fields['start_time_text'] ?? '')));
        $startDateTime = gov_prc_fallback_parse_datetime((string)($fields['start_time_text'] ?? ''));
        $endDateTime = gov_prc_fallback_parse_datetime((string)($fields['estimated_end_time_text'] ?? ''));

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
        $fields['estimated_price_amount'] = gov_prc_fallback_price_amount((string)($fields['estimated_price_text'] ?? ''));
        $fields['estimated_price_currency'] = gov_prc_fallback_price_currency((string)($fields['estimated_price_text'] ?? ''));

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
        foreach ($required as $field => $label) {
            if (trim((string)($fields[$field] ?? '')) === '') { $missing[] = $label; }
        }

        return ['fields' => $fields, 'missing_required' => $missing, 'diagnostics' => $diagnostics];
    }
}

if (!function_exists('gov_prc_merge_parser_with_fallback')) {
    /** @return array<string,mixed> */
    function gov_prc_merge_parser_with_fallback(array $parsed, string $sourceText): array
    {
        $fields = is_array($parsed['fields'] ?? null) ? $parsed['fields'] : [];
        $missing = is_array($parsed['missing_required'] ?? null) ? $parsed['missing_required'] : [];
        $nonEmpty = 0;
        foreach ($fields as $value) {
            if (trim((string)$value) !== '') { $nonEmpty++; }
        }

        // Use fallback when the line-based parser found very little. This is diagnostic-only
        // and does not alter the production V0 pre-ride tool parser file.
        if (!empty($parsed['ok']) || ($nonEmpty >= 4 && count($missing) <= 2)) {
            $parsed['_candidate_fallback'] = ['used' => false, 'reason' => 'primary_parser_sufficient'];
            return $parsed;
        }

        $fallback = gov_prc_fallback_fields_from_text($sourceText);
        $fallbackFields = $fallback['fields'];
        $fallbackNonEmpty = 0;
        foreach ($fallbackFields as $value) {
            if (trim((string)$value) !== '') { $fallbackNonEmpty++; }
        }

        if ($fallbackNonEmpty <= $nonEmpty) {
            $parsed['_candidate_fallback'] = [
                'used' => false,
                'reason' => 'fallback_not_better',
                'primary_non_empty_fields' => $nonEmpty,
                'fallback_non_empty_fields' => $fallbackNonEmpty,
                'diagnostics' => $fallback['diagnostics'],
            ];
            return $parsed;
        }

        $warnings = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];
        $warnings[] = 'Diagnostics fallback label parser was used because the primary parser found too few fields.';
        $parsed['fields'] = $fallbackFields;
        $parsed['missing_required'] = $fallback['missing_required'];
        $parsed['ok'] = count($fallback['missing_required']) === 0;
        $parsed['warnings'] = array_values(array_unique($warnings));
        $parsed['confidence'] = count($fallback['missing_required']) === 0 ? 'medium' : 'low';
        $parsed['_candidate_fallback'] = [
            'used' => true,
            'reason' => 'primary_parser_sparse',
            'primary_non_empty_fields' => $nonEmpty,
            'fallback_non_empty_fields' => $fallbackNonEmpty,
            'diagnostics' => $fallback['diagnostics'],
        ];
        return $parsed;
    }
}

if (!function_exists('gov_prc_analyze_text')) {
    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    function gov_prc_analyze_text(mysqli $db, array $source): array
    {
        $warnings = is_array($source['warnings'] ?? null) ? $source['warnings'] : [];
        if (!class_exists(BoltPreRideEmailParser::class)) {
            return [
                'ok' => false,
                'classification' => ['code' => 'PARSER_UNAVAILABLE', 'message' => 'Bolt pre-ride email parser class is unavailable.'],
                'source' => $source,
                'candidate' => null,
                'write' => null,
            ];
        }

        $sourceText = (string)($source['email_text'] ?? '');
        $parser = new BoltPreRideEmailParser();
        $parsed = $parser->parse($sourceText);
        $parsed = gov_prc_merge_parser_with_fallback($parsed, $sourceText);
        $fields = is_array($parsed['fields'] ?? null) ? $parsed['fields'] : [];
        $mapping = gov_prc_mapping_lookup($db, $fields);
        $adminExclusion = gov_prc_admin_exclusion_status($db, (string)($fields['vehicle_plate'] ?? ''));
        foreach ($adminExclusion['warnings'] as $warning) { $warnings[] = $warning; }

        $guardConfigured = gov_prc_configured_future_guard_minutes();
        $guardEffective = gov_prc_effective_future_guard_minutes();
        $pickupAt = (string)($fields['pickup_datetime_local'] ?? '');
        $futurePass = gov_prc_future_guard_passes($pickupAt, $guardEffective);
        $payload = gov_prc_build_payload_preview($fields, $mapping);
        $missing = is_array($parsed['missing_required'] ?? null) ? $parsed['missing_required'] : [];
        $parserWarnings = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];
        foreach ($parserWarnings as $warning) { $warnings[] = (string)$warning; }
        $mappingWarnings = is_array($mapping['warnings'] ?? null) ? $mapping['warnings'] : [];

        $blockers = [];
        if (empty($parsed['ok'])) { $blockers[] = 'pre_ride_parser_missing_required_fields'; }
        if ($guardConfigured < 30) { $blockers[] = 'configured_future_guard_below_30_minimum'; }
        if ($pickupAt === '') { $blockers[] = 'pre_ride_missing_pickup_datetime'; }
        elseif (!$futurePass) { $blockers[] = 'pre_ride_pickup_not_' . $guardEffective . '_min_future'; }
        if (empty($mapping['ok'])) { $blockers[] = 'pre_ride_mapping_not_ready'; }
        if (!empty($adminExclusion['excluded'])) { $blockers[] = 'pre_ride_vehicle_admin_excluded'; }
        if (trim((string)($fields['customer_name'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_customer_name'; }
        if (trim((string)($fields['customer_phone'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_customer_phone'; }
        if (trim((string)($fields['pickup_address'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_pickup_address'; }
        if (trim((string)($fields['dropoff_address'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_dropoff_address'; }
        if (trim((string)($fields['end_datetime_local'] ?? '')) === '') { $blockers[] = 'pre_ride_missing_estimated_end_datetime'; }
        if (trim((string)($mapping['lessor_id'] ?? '')) === '') { $blockers[] = 'pre_ride_lessor_not_resolved'; }
        if (trim((string)($mapping['driver_id'] ?? '')) === '') { $blockers[] = 'pre_ride_driver_not_resolved'; }
        if (trim((string)($mapping['vehicle_id'] ?? '')) === '') { $blockers[] = 'pre_ride_vehicle_not_resolved'; }
        if (trim((string)($mapping['starting_point_id'] ?? '')) === '') { $blockers[] = 'pre_ride_starting_point_not_resolved'; }

        $blockers = array_values(array_unique($blockers));
        $ready = empty(array_diff($blockers, ['configured_future_guard_below_30_minimum']));
        $status = $ready ? 'ready' : 'blocked';
        $readiness = $ready ? 'READY_FUTURE_PRE_RIDE_CANDIDATE' : 'BLOCKED_PRE_RIDE_CANDIDATE';

        $candidate = [
            'source_system' => 'bolt_pre_ride_email',
            'source_type' => (string)($source['source_type'] ?? ''),
            'source_label' => (string)($source['source_label'] ?? ''),
            'source_hash' => (string)($source['source_hash'] ?? ''),
            'source_mtime' => (string)($source['source_mtime'] ?? ''),
            'order_reference' => (string)($fields['order_reference'] ?? ''),
            'status' => $status,
            'readiness_status' => $readiness,
            'ready_for_edxeix' => $ready,
            'pickup_datetime' => $pickupAt,
            'estimated_end_datetime' => (string)($fields['end_datetime_local'] ?? ''),
            'customer_name' => (string)($fields['customer_name'] ?? ''),
            'customer_phone' => (string)($fields['customer_phone'] ?? ''),
            'driver_name' => (string)($fields['driver_name'] ?? ''),
            'vehicle_plate' => (string)($fields['vehicle_plate'] ?? ''),
            'pickup_address' => (string)($fields['pickup_address'] ?? ''),
            'dropoff_address' => (string)($fields['dropoff_address'] ?? ''),
            'price_amount' => (string)($fields['estimated_price_amount'] ?? ''),
            'price_currency' => (string)($fields['estimated_price_currency'] ?? ''),
            'configured_future_guard_minutes' => $guardConfigured,
            'effective_future_guard_minutes' => $guardEffective,
            'future_guard_floor_applied' => $guardEffective > $guardConfigured,
            'future_guard_passed' => $futurePass,
            'mapping_ready' => !empty($mapping['ok']),
            'admin_exclusion' => $adminExclusion,
            'safety_blockers' => $blockers,
            'missing_required' => $missing,
            'warnings' => array_values(array_unique($warnings)),
            'mapping_warnings' => $mappingWarnings,
            'mapping' => [
                'lookup_version' => (string)($mapping['lookup_version'] ?? ''),
                'lessor_id' => (string)($mapping['lessor_id'] ?? ''),
                'lessor_source' => (string)($mapping['lessor_source'] ?? ''),
                'driver_id' => (string)($mapping['driver_id'] ?? ''),
                'driver_label' => (string)($mapping['driver_label'] ?? ''),
                'vehicle_id' => (string)($mapping['vehicle_id'] ?? ''),
                'vehicle_label' => (string)($mapping['vehicle_label'] ?? ''),
                'starting_point_id' => (string)($mapping['starting_point_id'] ?? ''),
                'starting_point_label' => (string)($mapping['starting_point_label'] ?? ''),
                'company_trusted_from_edxeix_mapping' => !empty($mapping['company_trusted_from_edxeix_mapping']),
            ],
            'payload_summary' => gov_prc_payload_summary($payload),
            'payload_preview' => $payload,
            'parsed_fields' => $fields,
            'parser_fallback' => is_array($parsed['_candidate_fallback'] ?? null) ? $parsed['_candidate_fallback'] : ['used' => false],
        ];

        return [
            'ok' => true,
            'classification' => [
                'code' => $ready ? 'PRE_RIDE_READY_CANDIDATE' : 'PRE_RIDE_CANDIDATE_BLOCKED',
                'message' => $ready
                    ? 'Pre-ride email parsed into a future EDXEIX-ready candidate preview. No submit was performed.'
                    : 'Pre-ride email was parsed but is blocked by readiness/safety checks. No submit was performed.',
            ],
            'source' => [
                'source_type' => (string)($source['source_type'] ?? ''),
                'source_label' => (string)($source['source_label'] ?? ''),
                'source_mtime' => (string)($source['source_mtime'] ?? ''),
                'source_hash_16' => substr((string)($source['source_hash'] ?? ''), 0, 16),
                'checked_dirs' => is_array($source['checked_dirs'] ?? null) ? $source['checked_dirs'] : [],
            ],
            'candidate' => $candidate,
            'write' => null,
            'next_action' => $ready
                ? 'Review the candidate, then explicitly capture metadata with --write=1 only after the additive SQL table is installed.'
                : 'Fix the listed blockers or wait for a real future pre-ride email before attempting any one-shot transport.',
        ];
    }
}

if (!function_exists('gov_prc_table_exists')) {
    function gov_prc_table_exists(mysqli $db): bool
    {
        return function_exists('gov_bridge_table_exists') && gov_bridge_table_exists($db, 'edxeix_pre_ride_candidates');
    }
}

if (!function_exists('gov_prc_write_candidate')) {
    /**
     * @param array<string,mixed> $candidate
     * @return array<string,mixed>
     */
    function gov_prc_write_candidate(mysqli $db, array $candidate): array
    {
        if (!gov_prc_table_exists($db)) {
            return [
                'requested' => true,
                'ok' => false,
                'written' => false,
                'message' => 'Table edxeix_pre_ride_candidates does not exist. Run the additive migration first.',
            ];
        }

        $fields = is_array($candidate['parsed_fields'] ?? null) ? $candidate['parsed_fields'] : [];
        $payload = is_array($candidate['payload_preview'] ?? null) ? $candidate['payload_preview'] : [];
        $mapping = is_array($candidate['mapping'] ?? null) ? $candidate['mapping'] : [];
        $blockers = is_array($candidate['safety_blockers'] ?? null) ? $candidate['safety_blockers'] : [];
        $warnings = is_array($candidate['warnings'] ?? null) ? $candidate['warnings'] : [];

        $sql = "
            INSERT INTO edxeix_pre_ride_candidates (
                source_hash, source_type, source_label, source_mtime,
                order_reference, pickup_datetime, estimated_end_datetime,
                customer_name, customer_phone, driver_name, vehicle_plate,
                pickup_address, dropoff_address, price_amount, price_currency,
                status, readiness_status, ready_for_edxeix,
                parsed_fields_json, payload_preview_json, mapping_status_json,
                safety_blockers_json, warnings_json, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                source_type = VALUES(source_type),
                source_label = VALUES(source_label),
                source_mtime = VALUES(source_mtime),
                order_reference = VALUES(order_reference),
                pickup_datetime = VALUES(pickup_datetime),
                estimated_end_datetime = VALUES(estimated_end_datetime),
                customer_name = VALUES(customer_name),
                customer_phone = VALUES(customer_phone),
                driver_name = VALUES(driver_name),
                vehicle_plate = VALUES(vehicle_plate),
                pickup_address = VALUES(pickup_address),
                dropoff_address = VALUES(dropoff_address),
                price_amount = VALUES(price_amount),
                price_currency = VALUES(price_currency),
                status = VALUES(status),
                readiness_status = VALUES(readiness_status),
                ready_for_edxeix = VALUES(ready_for_edxeix),
                parsed_fields_json = VALUES(parsed_fields_json),
                payload_preview_json = VALUES(payload_preview_json),
                mapping_status_json = VALUES(mapping_status_json),
                safety_blockers_json = VALUES(safety_blockers_json),
                warnings_json = VALUES(warnings_json),
                updated_at = NOW()
        ";

        $pickupForDb = trim((string)($candidate['pickup_datetime'] ?? ''));
        $endForDb = trim((string)($candidate['estimated_end_datetime'] ?? ''));
        $params = [
            (string)($candidate['source_hash'] ?? ''),
            (string)($candidate['source_type'] ?? ''),
            (string)($candidate['source_label'] ?? ''),
            (string)($candidate['source_mtime'] ?? ''),
            (string)($candidate['order_reference'] ?? ''),
            $pickupForDb !== '' ? $pickupForDb : null,
            $endForDb !== '' ? $endForDb : null,
            (string)($candidate['customer_name'] ?? ''),
            (string)($candidate['customer_phone'] ?? ''),
            (string)($candidate['driver_name'] ?? ''),
            (string)($candidate['vehicle_plate'] ?? ''),
            (string)($candidate['pickup_address'] ?? ''),
            (string)($candidate['dropoff_address'] ?? ''),
            (string)($candidate['price_amount'] ?? ''),
            (string)($candidate['price_currency'] ?? ''),
            (string)($candidate['status'] ?? 'blocked'),
            (string)($candidate['readiness_status'] ?? 'BLOCKED_PRE_RIDE_CANDIDATE'),
            !empty($candidate['ready_for_edxeix']) ? 1 : 0,
            gov_prc_json($fields),
            gov_prc_json($payload),
            gov_prc_json($mapping),
            gov_prc_json($blockers),
            gov_prc_json($warnings),
        ];

        try {
            $stmt = $db->prepare($sql);
            $types = str_repeat('s', count($params));
            if (function_exists('gov_bridge_bind_params')) {
                gov_bridge_bind_params($stmt, $types, $params);
            } else {
                $refs = [];
                foreach ($params as $idx => $value) {
                    $params[$idx] = (string)$value;
                    $refs[$idx] = &$params[$idx];
                }
                $stmt->bind_param($types, ...$refs);
            }
            $stmt->execute();
            $idRow = gov_bridge_fetch_one($db, 'SELECT id FROM edxeix_pre_ride_candidates WHERE source_hash = ? LIMIT 1', [(string)($candidate['source_hash'] ?? '')]);
            return [
                'requested' => true,
                'ok' => true,
                'written' => true,
                'candidate_id' => (string)($idRow['id'] ?? ''),
                'message' => 'Candidate metadata captured. Raw email body was not stored.',
            ];
        } catch (Throwable $e) {
            return [
                'requested' => true,
                'ok' => false,
                'written' => false,
                'message' => 'Candidate capture failed: ' . $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('gov_prc_run')) {
    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    function gov_prc_run(array $options = []): array
    {
        $db = gov_bridge_db();
        $source = gov_prc_source_from_options($options);
        $writeRequested = !empty($options['write']);

        if (empty($source['ok'])) {
            return [
                'ok' => true,
                'classification' => [
                    'code' => 'NO_PRE_RIDE_EMAIL_SOURCE',
                    'message' => 'No pre-ride email source was provided. Use pasted text, --email-file, or --latest-mail=1.',
                ],
                'source' => [
                    'source_type' => (string)($source['source_type'] ?? ''),
                    'source_label' => (string)($source['source_label'] ?? ''),
                    'warnings' => is_array($source['warnings'] ?? null) ? $source['warnings'] : [],
                    'checked_dirs' => is_array($source['checked_dirs'] ?? null) ? $source['checked_dirs'] : [],
                ],
                'candidate' => null,
                'write' => [
                    'requested' => $writeRequested,
                    'ok' => false,
                    'written' => false,
                    'message' => $writeRequested ? 'Nothing to write because no pre-ride email source was loaded.' : 'Write not requested.',
                ],
                'next_action' => 'Load or paste the next real future Bolt pre-ride email and rerun diagnostics.',
            ];
        }

        $result = gov_prc_analyze_text($db, $source);
        if (!empty($source['debug_source'])) {
            $lines = (int)($source['debug_lines'] ?? 24);
            $lines = max(5, min(60, $lines));
            $result['source_debug'] = gov_prc_source_debug_report((string)($source['email_text'] ?? ''), $lines);
        }
        if ($writeRequested && is_array($result['candidate'] ?? null)) {
            $result['write'] = gov_prc_write_candidate($db, $result['candidate']);
        } else {
            $result['write'] = [
                'requested' => false,
                'ok' => true,
                'written' => false,
                'message' => 'Dry-run only. Candidate metadata was not captured.',
            ];
        }
        return $result;
    }
}
