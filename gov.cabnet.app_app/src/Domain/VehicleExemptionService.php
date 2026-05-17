<?php

namespace Bridge\Domain;

/**
 * Central operational vehicle exemptions for gov.cabnet.app.
 *
 * EMT8640 / Mercedes-Benz Sprinter is permanently outside the Bolt →
 * driver notification → voucher/receipt → AADE invoice/receipt → EDXEIX
 * automation path.
 *
 * Safety rule for EMT8640 / Mercedes-Benz Sprinter:
 * - No voucher / receipt-copy email.
 * - No driver email.
 * - No AADE/myDATA invoice or official receipt.
 * - No EDXEIX worker submission.
 */
final class VehicleExemptionService
{
    public const EMT8640_PLATE = 'EMT8640';
    public const EMT8640_BOLT_VEHICLE_IDENTIFIER = 'f9170acc-3bc4-43c5-9eed-65d9cadee490';
    public const SPRINTER_MODEL = 'Mercedes-Benz Sprinter';
    public const REASON_CODE = 'vehicle_exempt_admin_excluded_sprinter_no_voucher_no_driver_email_no_invoice';

    private const EXEMPT_PLATES = [
        self::EMT8640_PLATE,
    ];

    private const EXEMPT_IDENTIFIERS = [
        self::EMT8640_BOLT_VEHICLE_IDENTIFIER,
    ];

    private const EXEMPT_MODELS = [
        self::SPRINTER_MODEL,
    ];

    private function __construct()
    {
    }

    public static function reasonCode(): string
    {
        return self::REASON_CODE;
    }

    public static function reasonText(): string
    {
        return 'Vehicle EMT8640 / Mercedes-Benz Sprinter is permanently admin-excluded: no voucher, no driver email, no invoice/AADE receipt, no automated EDXEIX submission.';
    }

    public static function isExemptPlate(?string $plate): bool
    {
        $normalized = self::normalizePlate((string)$plate);
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::EXEMPT_PLATES, true);
    }

    public static function isExemptIdentifier(?string $identifier): bool
    {
        $normalized = self::normalizeIdentifier((string)$identifier);
        if ($normalized === '') {
            return false;
        }

        foreach (self::EXEMPT_IDENTIFIERS as $exemptIdentifier) {
            if ($normalized === self::normalizeIdentifier($exemptIdentifier)) {
                return true;
            }
        }

        return false;
    }

    public static function isExemptModel(?string $model): bool
    {
        $normalized = self::normalizeModel((string)$model);
        if ($normalized === '') {
            return false;
        }

        foreach (self::EXEMPT_MODELS as $exemptModel) {
            $candidate = self::normalizeModel($exemptModel);
            if ($normalized === $candidate || str_contains($normalized, $candidate) || str_contains($candidate, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function isExemptRow(array $row): bool
    {
        foreach (self::plateKeys() as $key) {
            if (array_key_exists($key, $row) && self::isExemptPlate(self::stringValue($row[$key]))) {
                return true;
            }
        }

        foreach (self::identifierKeys() as $key) {
            if (array_key_exists($key, $row) && self::isExemptIdentifier(self::stringValue($row[$key]))) {
                return true;
            }
        }

        foreach (self::modelKeys() as $key) {
            if (array_key_exists($key, $row) && self::isExemptModel(self::stringValue($row[$key]))) {
                return true;
            }
        }

        $rawJson = $row['raw_payload_json'] ?? $row['payload_json'] ?? $row['parsed_fields_json'] ?? null;
        if (is_string($rawJson) && $rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded) && self::isExemptRow($decoded)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private static function plateKeys(): array
    {
        return [
            'vehicle_plate',
            'plate',
            'licence_plate',
            'license_plate',
            'vehicle_registration',
            'registration_plate',
            'vehicle',
        ];
    }

    /** @return array<int,string> */
    private static function identifierKeys(): array
    {
        return [
            'vehicle_identifier',
            'vehicle_uuid',
            'external_vehicle_id',
            'bolt_vehicle_id',
            'bolt_vehicle_uuid',
            'external_id',
            'individual_identifier',
        ];
    }

    /** @return array<int,string> */
    private static function modelKeys(): array
    {
        return [
            'vehicle_model',
            'model',
            'external_vehicle_name',
            'vehicle_name',
            'vehicle_description',
            'vehicle_type',
        ];
    }

    private static function stringValue(mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return '';
        }
        return (string)$value;
    }

    public static function normalizePlate(string $plate): string
    {
        $plate = html_entity_decode(strip_tags($plate), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plate = trim($plate);
        $plate = function_exists('mb_strtoupper') ? mb_strtoupper($plate, 'UTF-8') : strtoupper($plate);
        $plate = preg_replace('/[^A-Z0-9]/', '', $plate) ?? $plate;
        return trim($plate);
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        $identifier = html_entity_decode(strip_tags($identifier), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $identifier = trim($identifier);
        return strtolower($identifier);
    }

    public static function normalizeModel(string $model): string
    {
        $model = html_entity_decode(strip_tags($model), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $model = trim($model);
        $model = function_exists('mb_strtolower') ? mb_strtolower($model, 'UTF-8') : strtolower($model);
        $model = str_replace(['-', '_', '/', '\\', '.', ',', ';', ':', '(', ')', '"', "'", "\xc2\xa0"], ' ', $model);
        $model = preg_replace('/\s+/', ' ', $model) ?? $model;
        return trim($model);
    }
}
