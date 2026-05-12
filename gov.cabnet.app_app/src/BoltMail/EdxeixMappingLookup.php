<?php
/**
 * gov.cabnet.app — EDXEIX mapping lookup.
 *
 * v6.6.16-lessor-starting-point
 *
 * Rule:
 * - Bolt operator/fleet label is NOT the EDXEIX legal lessor source.
 * - EDXEIX company/lessor comes from mapped driver/vehicle ownership.
 * - Vehicle lessor wins when available.
 * - Driver lessor is second.
 * - Bolt operator alias is fallback only and does not make lookup production-ready.
 * - Starting point is resolved after the EDXEIX lessor is known.
 * - Lessor-specific starting point overrides global/default starting point.
 *
 * Safety:
 * - SELECT only.
 * - No DB writes.
 * - No EDXEIX calls.
 * - No AADE calls.
 */

declare(strict_types=1);

namespace Bridge\BoltMail;

use mysqli;
use Throwable;

final class EdxeixMappingLookup
{
    public const VERSION = 'v6.6.16-lessor-starting-point';

    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function lookup(array $fields): array
    {
        $messages = ['Lookup engine: ' . self::VERSION];
        $warnings = [];

        $operator = trim((string)($fields['operator'] ?? ''));
        $driverName = trim((string)($fields['driver_name'] ?? ''));
        $vehiclePlate = trim((string)($fields['vehicle_plate'] ?? ''));

        $driver = $this->findDriver($driverName, $warnings);
        $vehicle = $this->findVehicle($vehiclePlate, $warnings);

        $operatorLessor = $this->knownOperatorAlias($operator);
        $driverLessor = trim((string)($driver['lessor_id'] ?? ''));
        $vehicleLessor = trim((string)($vehicle['lessor_id'] ?? ''));

        $lessorId = '';
        $lessorSource = '';
        $companyTrusted = false;
        $mappingConflict = false;

        if ($driverLessor !== '' && $vehicleLessor !== '' && $driverLessor !== $vehicleLessor) {
            $mappingConflict = true;
            $warnings[] = 'EDXEIX mapping conflict: driver lessor is ' . $driverLessor . ', vehicle lessor is ' . $vehicleLessor . '. Manual review required.';
        }

        if (!$mappingConflict && $vehicleLessor !== '') {
            $lessorId = $vehicleLessor;
            $lessorSource = 'vehicle EDXEIX mapping';
            $companyTrusted = true;
        } elseif (!$mappingConflict && $driverLessor !== '') {
            $lessorId = $driverLessor;
            $lessorSource = 'driver EDXEIX mapping';
            $companyTrusted = true;
        } elseif ($operatorLessor !== '') {
            $lessorId = $operatorLessor;
            $lessorSource = 'Bolt operator alias fallback';
            $warnings[] = 'Company/lessor was resolved only from Bolt operator alias. This is not production-safe; map driver or vehicle to its EDXEIX lessor.';
        }

        $startingPoint = $this->findStartingPoint($warnings, $lessorId);

        if ($lessorId !== '') {
            $messages[] = 'Company/lessor ID resolved: ' . $lessorSource . ' → ' . $lessorId;
        } else {
            $warnings[] = 'Company/lessor ID could not be resolved from EDXEIX mapping.';
        }

        if (($driver['id'] ?? '') !== '') {
            $messages[] = 'Driver ID found in DB: ' . ($driver['label'] ?? $driverName) . ' → ' . $driver['id'];
            if ($driverLessor !== '') {
                $messages[] = 'Driver EDXEIX lessor: ' . $driverLessor;
            }
        } elseif ($driverName !== '') {
            $warnings[] = 'Driver was not mapped in DB: ' . $driverName;
        }

        if (($vehicle['id'] ?? '') !== '') {
            $messages[] = 'Vehicle ID found in DB: ' . ($vehicle['label'] ?? $vehiclePlate) . ' → ' . $vehicle['id'];
            if ($vehicleLessor !== '') {
                $messages[] = 'Vehicle EDXEIX lessor: ' . $vehicleLessor;
            }
        } elseif ($vehiclePlate !== '') {
            $warnings[] = 'Vehicle was not mapped in DB: ' . $vehiclePlate;
        }

        if (($startingPoint['id'] ?? '') !== '') {
            $messages[] = 'Starting point ID resolved: ' . ($startingPoint['label'] ?? 'starting point') . ' → ' . $startingPoint['id'];
            if (!empty($startingPoint['source'])) {
                $messages[] = 'Starting point source: ' . $startingPoint['source'];
            }
        } else {
            $startingPoint = ['id' => '', 'label' => '', 'source' => ''];
            $warnings[] = 'Starting point ID was not found in DB. Browser helper must not guess; choose manually or add a verified mapping.';
        }

        if ($operatorLessor !== '' && $lessorId !== '' && $operatorLessor !== $lessorId) {
            $warnings[] = 'Bolt operator alias points to ' . $operatorLessor . ', but EDXEIX driver/vehicle mapping points to ' . $lessorId . '. Using EDXEIX mapping.';
        }

        $ok = (
            !$mappingConflict &&
            $companyTrusted &&
            (($driver['id'] ?? '') !== '') &&
            (($vehicle['id'] ?? '') !== '') &&
            (($startingPoint['id'] ?? '') !== '') &&
            $lessorId !== ''
        );

        return [
            'ok' => $ok,
            'lookup_version' => self::VERSION,
            'lessor_id' => $lessorId,
            'lessor_source' => $lessorSource,
            'company_trusted_from_edxeix_mapping' => $companyTrusted,
            'driver_id' => $driver['id'] ?? '',
            'driver_label' => $driver['label'] ?? '',
            'vehicle_id' => $vehicle['id'] ?? '',
            'vehicle_label' => $vehicle['label'] ?? '',
            'starting_point_id' => $startingPoint['id'] ?? '',
            'starting_point_label' => $startingPoint['label'] ?? '',
            'starting_point_source' => $startingPoint['source'] ?? '',
            'messages' => $messages,
            'warnings' => $warnings,
            'driver_match' => $driver,
            'vehicle_match' => $vehicle,
        ];
    }

    /** @param array<int,string> $warnings */
    private function findDriver(string $driverName, array &$warnings): array
    {
        if ($driverName === '') {
            return ['id' => '', 'label' => '', 'lessor_id' => ''];
        }

        try {
            $res = $this->db->query("
                SELECT id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active
                FROM mapping_drivers
                WHERE edxeix_driver_id IS NOT NULL
                  AND edxeix_driver_id <> 0
                ORDER BY id DESC
                LIMIT 5000
            ");

            if (!$res) {
                return ['id' => '', 'label' => '', 'lessor_id' => ''];
            }

            $target = $this->nameNorm($driverName);
            $loose = null;

            while ($row = $res->fetch_assoc()) {
                if ((string)($row['is_active'] ?? '1') === '0') {
                    continue;
                }

                $candidate = trim((string)($row['external_driver_name'] ?? ''));
                if ($candidate === '') {
                    continue;
                }

                $candidateNorm = $this->nameNorm($candidate);

                if ($candidateNorm === $target) {
                    return [
                        'id' => trim((string)$row['edxeix_driver_id']),
                        'label' => $candidate,
                        'lessor_id' => trim((string)($row['edxeix_lessor_id'] ?? '')),
                        'db_row_id' => (string)($row['id'] ?? ''),
                        'source' => 'mapping_drivers exact normalized',
                    ];
                }

                if ($loose === null && $this->looseNameMatch($candidateNorm, $target)) {
                    $loose = [
                        'id' => trim((string)$row['edxeix_driver_id']),
                        'label' => $candidate,
                        'lessor_id' => trim((string)($row['edxeix_lessor_id'] ?? '')),
                        'db_row_id' => (string)($row['id'] ?? ''),
                        'source' => 'mapping_drivers loose normalized',
                    ];
                }
            }

            return $loose ?: ['id' => '', 'label' => '', 'lessor_id' => ''];
        } catch (Throwable $e) {
            $warnings[] = 'Driver lookup DB error: ' . $e->getMessage();
            return ['id' => '', 'label' => '', 'lessor_id' => ''];
        }
    }

    /** @param array<int,string> $warnings */
    private function findVehicle(string $plate, array &$warnings): array
    {
        if ($plate === '') {
            return ['id' => '', 'label' => '', 'lessor_id' => ''];
        }

        try {
            $res = $this->db->query("
                SELECT id, plate, edxeix_vehicle_id, edxeix_lessor_id, is_active
                FROM mapping_vehicles
                WHERE edxeix_vehicle_id IS NOT NULL
                  AND edxeix_vehicle_id <> 0
                ORDER BY id DESC
                LIMIT 5000
            ");

            if (!$res) {
                return ['id' => '', 'label' => '', 'lessor_id' => ''];
            }

            $target = $this->plateNorm($plate);

            while ($row = $res->fetch_assoc()) {
                if ((string)($row['is_active'] ?? '1') === '0') {
                    continue;
                }

                $candidate = trim((string)($row['plate'] ?? ''));
                if ($candidate === '') {
                    continue;
                }

                if ($this->plateNorm($candidate) === $target) {
                    return [
                        'id' => trim((string)$row['edxeix_vehicle_id']),
                        'label' => $candidate,
                        'lessor_id' => trim((string)($row['edxeix_lessor_id'] ?? '')),
                        'db_row_id' => (string)($row['id'] ?? ''),
                        'source' => 'mapping_vehicles exact normalized',
                    ];
                }
            }

            return ['id' => '', 'label' => '', 'lessor_id' => ''];
        } catch (Throwable $e) {
            $warnings[] = 'Vehicle lookup DB error: ' . $e->getMessage();
            return ['id' => '', 'label' => '', 'lessor_id' => ''];
        }
    }

    /** @param array<int,string> $warnings */
    private function findStartingPoint(array &$warnings, string $lessorId = ''): array
    {
        $lessorId = trim($lessorId);

        try {
            if ($lessorId !== '' && $this->tableExists('mapping_lessor_starting_points')) {
                $sql = "
                    SELECT id, edxeix_lessor_id, internal_key, label, edxeix_starting_point_id
                    FROM mapping_lessor_starting_points
                    WHERE is_active = 1
                      AND edxeix_lessor_id = ?
                      AND edxeix_starting_point_id IS NOT NULL
                      AND edxeix_starting_point_id <> ''
                    ORDER BY
                      CASE WHEN internal_key IN ('default', 'whiteblue_default', 'edra_mas') THEN 0 ELSE 1 END,
                      id ASC
                    LIMIT 1
                ";
                $stmt = $this->db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('s', $lessorId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    if (is_array($row) && trim((string)$row['edxeix_starting_point_id']) !== '') {
                        return [
                            'id' => trim((string)$row['edxeix_starting_point_id']),
                            'label' => (string)($row['label'] ?: $row['internal_key']),
                            'source' => 'mapping_lessor_starting_points lessor ' . $lessorId,
                            'db_row_id' => (string)($row['id'] ?? ''),
                        ];
                    }
                }
            }

            $res = $this->db->query("
                SELECT id, internal_key, label, edxeix_starting_point_id
                FROM mapping_starting_points
                WHERE is_active = 1
                  AND edxeix_starting_point_id IS NOT NULL
                  AND edxeix_starting_point_id <> ''
                ORDER BY
                  CASE WHEN internal_key = 'edra_mas' THEN 0
                       WHEN internal_key = 'default' THEN 1
                       ELSE 2 END,
                  id ASC
                LIMIT 1
            ");

            $row = $res ? $res->fetch_assoc() : null;

            if (is_array($row) && trim((string)$row['edxeix_starting_point_id']) !== '') {
                return [
                    'id' => trim((string)$row['edxeix_starting_point_id']),
                    'label' => (string)($row['label'] ?: $row['internal_key']),
                    'source' => 'mapping_starting_points global fallback',
                    'db_row_id' => (string)($row['id'] ?? ''),
                ];
            }

            return ['id' => '', 'label' => '', 'source' => ''];
        } catch (Throwable $e) {
            $warnings[] = 'Starting point lookup DB error: ' . $e->getMessage();
            return ['id' => '', 'label' => '', 'source' => ''];
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('s', $table);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_assoc();
        } catch (Throwable) {
            return false;
        }
    }

    private function knownOperatorAlias(string $operator): string
    {
        $key = $this->nameNorm($operator);

        $map = [
            'fleet mykonos luxlimo i k e mykonos cab' => '3814',
            'fleet mykonos luxlimo ike mykonos cab' => '3814',
            'fleet mykonos luxlimo i k e' => '3814',
            'fleet mykonos luxlimo ike' => '3814',
            'luxlimo i k e' => '3814',
            'luxlimo ike' => '3814',
            'luxlimo' => '3814',
            'qualitative transfer mykonos ik e' => '2307',
            'qualitative transfer mykonos' => '2307',
            'n g k μονοπροσωπη i k e' => '2124',
            'n g k' => '2124',
            'ngk' => '2124',
            'mta' => '3894',
            'mykonos tourist agency' => '3894',
            'vip road mykonos' => '1487',
            'vip road' => '1487',
            'whiteblue premium e e' => '1756',
            'whiteblue premium' => '1756',
            'white blue' => '1756',
            'lux mykonos o e' => '4635',
            'lux mykonos' => '4635',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        foreach ($map as $name => $id) {
            if ($key !== '' && (str_contains($key, $name) || str_contains($name, $key))) {
                return $id;
            }
        }

        return '';
    }

    private function looseNameMatch(string $candidate, string $target): bool
    {
        if ($candidate === '' || $target === '') {
            return false;
        }

        if (str_contains($candidate, $target) || str_contains($target, $candidate)) {
            return true;
        }

        $candidateTokens = array_values(array_filter(explode(' ', $candidate)));
        $targetTokens = array_values(array_filter(explode(' ', $target)));

        if (count($candidateTokens) < 2 || count($targetTokens) < 2) {
            return false;
        }

        $hits = 0;
        foreach ($targetTokens as $token) {
            if (strlen($token) < 3) {
                continue;
            }
            foreach ($candidateTokens as $candidateToken) {
                if ($token === $candidateToken) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits >= 2;
    }

    private function nameNorm(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim($value);

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        $value = str_replace(['||', '|', '.', ',', ';', ':', '-', '_', '/', '\\', '(', ')', '"', "'", "\xc2\xa0"], ' ', $value);
        $value = str_replace(['ί', 'ϊ', 'ΐ'], 'ι', $value);
        $value = str_replace(['ή'], 'η', $value);
        $value = str_replace(['ύ', 'ϋ', 'ΰ'], 'υ', $value);
        $value = str_replace(['ό'], 'ο', $value);
        $value = str_replace(['ά'], 'α', $value);
        $value = str_replace(['έ'], 'ε', $value);
        $value = str_replace(['ώ'], 'ω', $value);
        $value = str_replace(['ς'], 'σ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function plateNorm(string $plate): string
    {
        $plate = html_entity_decode(strip_tags($plate), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plate = trim($plate);

        if (function_exists('mb_strtoupper')) {
            $plate = mb_strtoupper($plate, 'UTF-8');
        } else {
            $plate = strtoupper($plate);
        }

        $plate = strtr($plate, [
            'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H',
            'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M', 'Ν' => 'N', 'Ο' => 'O',
            'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
        ]);

        return preg_replace('/[^A-Z0-9]/', '', $plate) ?? $plate;
    }
}
