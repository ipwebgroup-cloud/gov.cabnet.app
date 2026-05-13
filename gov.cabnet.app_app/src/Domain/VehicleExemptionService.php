<?php

namespace Bridge\Domain;

/**
 * Central operational vehicle exemptions for gov.cabnet.app.
 *
 * EMT8640 is permanently outside the Bolt → driver notification → voucher/
 * receipt → AADE invoice/receipt → EDXEIX automation path.
 *
 * Safety rule for EMT8640:
 * - No voucher / receipt-copy email.
 * - No driver email.
 * - No AADE/myDATA invoice or official receipt.
 * - No EDXEIX worker submission.
 */
final class VehicleExemptionService
{
    public const EMT8640_PLATE = 'EMT8640';
    public const EMT8640_BOLT_VEHICLE_IDENTIFIER = 'f9170acc-3bc4-43c5-9eed-65d9cadee490';
    public const REASON_CODE = 'vehicle_exempt_emt8640_no_voucher_no_driver_email_no_invoice';

    private const EXEMPT_PLATES = [
        self::EMT8640_PLATE,
    ];

    private const EXEMPT_IDENTIFIERS = [
        self::EMT8640_BOLT_VEHICLE_IDENTIFIER,
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
        return 'Vehicle EMT8640 is permanently exempt: no voucher, no driver email, no invoice/AADE receipt, no automated EDXEIX submission.';
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
}
