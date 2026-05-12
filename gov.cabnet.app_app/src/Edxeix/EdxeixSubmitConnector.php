<?php
/**
 * gov.cabnet.app — EDXEIX submit connector contract / disabled dry-run stub.
 *
 * This class prepares the shape of a future server-side EDXEIX submit request,
 * but it never sends HTTP requests. Live submission remains explicitly disabled.
 *
 * Safety contract:
 * - Does not call EDXEIX.
 * - Does not call Bolt.
 * - Does not call AADE.
 * - Does not write database rows.
 * - Does not store cookies, sessions, credentials, or CSRF token values.
 */

declare(strict_types=1);

namespace Bridge\Edxeix;

final class EdxeixSubmitConnector
{
    public const VERSION = 'v0.1.0-disabled-dry-run-connector-contract';

    /**
     * @param array<string,mixed> $fields Parsed Bolt pre-ride fields.
     * @param array<string,mixed> $mapping EDXEIX ID resolver result.
     * @param array<string,mixed>|null $capture Sanitized EDXEIX submit capture metadata.
     * @param array<string,mixed> $options Reserved for future use.
     * @return array<string,mixed>
     */
    public function buildRequest(array $fields, array $mapping, ?array $capture = null, array $options = []): array
    {
        $capture = is_array($capture) ? $capture : [];
        $method = strtoupper($this->str($capture, 'form_method'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $method = 'POST';
        }

        $actionHost = $this->str($capture, 'action_host');
        $actionPath = $this->str($capture, 'action_path');
        $csrfFieldName = $this->firstNonEmpty([
            $this->str($capture, 'csrf_field_name'),
            '_token',
        ]);

        $coordinateFields = $this->splitFieldList($this->str($capture, 'coordinate_field_names'));
        $requiredFields = $this->splitFieldList($this->str($capture, 'required_field_names'));
        $selectFields = $this->splitFieldList($this->str($capture, 'select_field_names'));

        $payload = [
            $csrfFieldName => '__ACTIVE_EDXEIX_CSRF_TOKEN_REQUIRED_NOT_STORED__',
            'lessor' => $this->str($mapping, 'lessor_id'),
            'lessee[type]' => 'natural',
            'lessee[name]' => $this->str($fields, 'customer_name'),
            'driver' => $this->str($mapping, 'driver_id'),
            'vehicle' => $this->str($mapping, 'vehicle_id'),
            'starting_point_id' => $this->str($mapping, 'starting_point_id'),
            'boarding_point' => $this->str($fields, 'pickup_address'),
            'coordinates' => '__OPERATOR_CONFIRMED_MAP_COORDINATES_REQUIRED__',
            'disembark_point' => $this->str($fields, 'dropoff_address'),
            'drafted_at' => $this->formatGreekDateTime($this->str($fields, 'pickup_datetime_local')),
            'started_at' => $this->formatGreekDateTime($this->str($fields, 'pickup_datetime_local')),
            'ended_at' => $this->formatGreekDateTime($this->str($fields, 'end_datetime_local')),
            'price' => $this->str($fields, 'estimated_price_amount'),
            'broker' => '',
        ];

        $coverage = $this->fieldCoverage($payload, $requiredFields, $selectFields, $coordinateFields, $csrfFieldName);

        return [
            'ok' => true,
            'version' => self::VERSION,
            'live_submit_enabled' => false,
            'live_submit_allowed' => false,
            'dry_run_only' => true,
            'method' => $method,
            'action_host' => $actionHost,
            'action_path' => $actionPath,
            'target_url_preview' => $actionHost !== '' && $actionPath !== ''
                ? 'https://' . $actionHost . $actionPath
                : '',
            'headers_preview' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml,application/json',
                'X-CSRF-TOKEN' => '__ACTIVE_EDXEIX_CSRF_TOKEN_REQUIRED_NOT_STORED__',
            ],
            'payload_preview' => $payload,
            'coverage' => $coverage,
            'blockers' => array_values(array_unique(array_merge(
                $coverage['blockers'],
                [
                    'server_side_edxeix_live_submit_not_implemented',
                    'active_edxeix_browser_session_not_connected',
                    'active_csrf_token_not_available_by_design',
                    'operator_confirmed_map_coordinates_missing',
                ]
            ))),
            'safety_contract' => [
                'calls_edxeix' => false,
                'calls_bolt' => false,
                'calls_aade' => false,
                'writes_database' => false,
                'stores_cookie_values' => false,
                'stores_session_values' => false,
                'stores_csrf_token_values' => false,
                'live_submit_default' => false,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function submitDisabled(array $request, array $options = []): array
    {
        return [
            'ok' => false,
            'submitted' => false,
            'version' => self::VERSION,
            'error' => 'Live EDXEIX submit is disabled. This connector is a dry-run contract only.',
            'blockers' => [
                'server_side_edxeix_live_submit_not_implemented',
                'explicit_andreas_approval_required',
                'active_edxeix_session_bridge_required',
                'operator_final_confirmation_required',
                'map_point_confirmation_required',
            ],
            'request_summary' => [
                'method' => (string)($request['method'] ?? ''),
                'action_host' => (string)($request['action_host'] ?? ''),
                'action_path' => (string)($request['action_path'] ?? ''),
                'payload_field_count' => is_array($request['payload_preview'] ?? null) ? count($request['payload_preview']) : 0,
            ],
        ];
    }

    /** @param array<string,mixed> $source */
    private function str(array $source, string $key): string
    {
        return trim((string)($source[$key] ?? ''));
    }

    /** @param array<int,string> $values */
    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /** @return array<int,string> */
    private function splitFieldList(string $value): array
    {
        $parts = preg_split('/[\r\n,;]+/', $value) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }

    private function formatGreekDateTime(string $raw): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::\d{2})?/', $raw, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . str_pad($m[4], 2, '0', STR_PAD_LEFT) . ':' . $m[5];
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{1,2}):(\d{2})/', $raw, $m)) {
            return $m[1] . '/' . $m[2] . '/' . $m[3] . ' ' . str_pad($m[4], 2, '0', STR_PAD_LEFT) . ':' . $m[5];
        }
        return $raw;
    }

    /**
     * @param array<string,string> $payload
     * @param array<int,string> $requiredFields
     * @param array<int,string> $selectFields
     * @param array<int,string> $coordinateFields
     * @return array<string,mixed>
     */
    private function fieldCoverage(array $payload, array $requiredFields, array $selectFields, array $coordinateFields, string $csrfFieldName): array
    {
        $blockers = [];
        $warnings = [];
        $present = [];
        $missing = [];

        foreach ($requiredFields as $fieldName) {
            if (array_key_exists($fieldName, $payload) && trim((string)$payload[$fieldName]) !== '') {
                $present[] = $fieldName;
            } else {
                $missing[] = $fieldName;
                $blockers[] = 'missing_required_payload_field_' . $this->slug($fieldName);
            }
        }

        foreach (['lessor', 'driver', 'vehicle', 'starting_point_id'] as $fieldName) {
            if (trim((string)($payload[$fieldName] ?? '')) === '') {
                $blockers[] = 'missing_core_select_' . $fieldName;
            }
        }

        if ($csrfFieldName === '') {
            $blockers[] = 'missing_csrf_field_name';
        }
        if ($coordinateFields === []) {
            $warnings[] = 'No coordinate/map field names are recorded in the sanitized submit capture.';
        }
        if ($selectFields === []) {
            $warnings[] = 'No select/dropdown field names are recorded in the sanitized submit capture.';
        }

        return [
            'ok' => count($blockers) === 0,
            'required_present' => $present,
            'required_missing' => $missing,
            'required_count' => count($requiredFields),
            'payload_field_count' => count($payload),
            'select_field_names_from_capture' => $selectFields,
            'coordinate_field_names_from_capture' => $coordinateFields,
            'csrf_field_name' => $csrfFieldName,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: $value;
        return trim($value, '_') ?: 'unknown';
    }
}
