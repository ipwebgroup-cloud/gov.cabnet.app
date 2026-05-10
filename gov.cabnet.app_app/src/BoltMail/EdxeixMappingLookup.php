<?php
/**
 * gov.cabnet.app — Read-only EDXEIX mapping lookup for pre-ride emails.
 *
 * Safety:
 * - Read-only SELECT/SHOW queries only.
 * - No writes.
 * - No EDXEIX calls.
 * - No AADE calls.
 *
 * v6.6.10:
 * - Adds exact driver/vehicle lookup before fuzzy scoring.
 * - Does not require the email operator lessor to match the mapped driver/vehicle lessor.
 * - Allows mapped driver/vehicle lessor to override the email operator lessor for partner/executing vehicles.
 */

declare(strict_types=1);

namespace Bridge\BoltMail;

use mysqli;
use Throwable;

final class EdxeixMappingLookup
{
    public const VERSION = 'v6.6.10';

    private mysqli $db;

    /** @var array<string,array<int,string>> */
    private array $columnsCache = [];

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string,string> $fields
     * @return array<string,mixed>
     */
    public function lookup(array $fields): array
    {
        $messages = [];
        $warnings = [];

        $operator = trim((string)($fields['operator'] ?? ''));
        $driverName = trim((string)($fields['driver_name'] ?? ''));
        $vehiclePlate = trim((string)($fields['vehicle_plate'] ?? ''));

        $driver = $this->lookupDriver($driverName);
        $vehicle = $this->lookupVehicle($vehiclePlate);
        $startingPoint = $this->lookupStartingPoint();
        $lessor = $this->resolveLessorId($operator, $driver, $vehicle);

        $messages[] = 'Lookup engine: ' . self::VERSION;

        if ($lessor['id'] !== '') {
            $messages[] = 'Company/lessor ID resolved: ' . $lessor['source'] . ' → ' . $lessor['id'];
        } else {
            $warnings[] = 'Company/lessor ID could not be resolved from DB or known operator aliases.';
        }

        if ($driver['id'] !== '') {
            $messages[] = 'Driver ID found in DB: ' . $driver['label'] . ' → ' . $driver['id'];
            if ($this->rowLessorId($driver['row'] ?? null) !== '') {
                $messages[] = 'Driver mapped lessor: ' . $this->rowLessorId($driver['row'] ?? null);
            }
        } elseif ($driverName !== '') {
            $warnings[] = 'Driver was not mapped in DB: ' . $driverName;
        }

        if ($vehicle['id'] !== '') {
            $messages[] = 'Vehicle ID found in DB: ' . $vehicle['label'] . ' → ' . $vehicle['id'];
            if ($this->rowLessorId($vehicle['row'] ?? null) !== '') {
                $messages[] = 'Vehicle mapped lessor: ' . $this->rowLessorId($vehicle['row'] ?? null);
            }
        } elseif ($vehiclePlate !== '') {
            $warnings[] = 'Vehicle was not mapped in DB: ' . $vehiclePlate;
        }

        if ($startingPoint['id'] !== '') {
            $messages[] = 'Starting point ID resolved: ' . $startingPoint['label'] . ' → ' . $startingPoint['id'];
        } else {
            $warnings[] = 'Starting point ID not found in DB; using default 5875309.';
            $startingPoint = ['id' => '5875309', 'label' => 'Έδρα μας', 'source' => 'fallback'];
        }

        $operatorLessor = $this->knownLessorId($operator);
        $effectiveLessor = (string)$lessor['id'];
        if ($operatorLessor !== '' && $effectiveLessor !== '' && $operatorLessor !== $effectiveLessor) {
            $warnings[] = 'Operator lessor alias is ' . $operatorLessor . ', but mapped driver/vehicle lessor is ' . $effectiveLessor . '. Using mapped lessor for EDXEIX form.';
        }

        return [
            'ok' => $driver['id'] !== '' && $vehicle['id'] !== '' && $lessor['id'] !== '',
            'lookup_version' => self::VERSION,
            'lessor_id' => $lessor['id'],
            'lessor_source' => $lessor['source'],
            'driver_id' => $driver['id'],
            'driver_label' => $driver['label'],
            'vehicle_id' => $vehicle['id'],
            'vehicle_label' => $vehicle['label'],
            'starting_point_id' => $startingPoint['id'],
            'starting_point_label' => $startingPoint['label'],
            'messages' => $messages,
            'warnings' => $warnings,
            'driver_match' => $driver,
            'vehicle_match' => $vehicle,
        ];
    }

    /**
     * @return array{id:string,label:string,source:string,row:array<string,mixed>|null,score:int}
     */
    private function lookupDriver(string $driverName): array
    {
        $empty = ['id' => '', 'label' => '', 'source' => 'none', 'row' => null, 'score' => 0];
        if ($driverName === '' || !$this->tableExists('mapping_drivers')) {
            return $empty;
        }

        $cols = $this->columns('mapping_drivers');
        $idCol = $this->firstColumn($cols, ['edxeix_driver_id', 'driver_edxeix_id', 'edxeix_id']);
        $nameCol = $this->firstColumn($cols, ['external_driver_name', 'driver_name', 'name', 'full_name']);
        if ($idCol === '' || $nameCol === '') {
            return $empty;
        }

        $exact = $this->fetchExactDriverRow($driverName, $idCol, $nameCol);
        if ($exact !== null) {
            return [
                'id' => trim((string)($exact[$idCol] ?? '')),
                'label' => (string)($exact[$nameCol] ?? $driverName),
                'source' => 'mapping_drivers exact',
                'row' => $exact,
                'score' => 150,
            ];
        }

        $rows = $this->fetchRows('mapping_drivers', [$idCol, $nameCol, 'external_driver_id', 'edxeix_lessor_id', 'lessor_id', 'company_id', 'edxeix_company_id', 'is_active', 'active_vehicle_plate', 'driver_identifier', 'individual_identifier'], $idCol . ' IS NOT NULL');
        $best = null;
        $bestScore = 0;
        foreach ($rows as $row) {
            if (isset($row['is_active']) && (string)$row['is_active'] === '0') {
                continue;
            }
            if (trim((string)($row[$idCol] ?? '')) === '' || trim((string)($row[$idCol] ?? '')) === '0') {
                continue;
            }
            $score = $this->nameScore($driverName, (string)($row[$nameCol] ?? ''));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        if ($best === null || $bestScore < 45) {
            return $empty;
        }

        return [
            'id' => trim((string)($best[$idCol] ?? '')),
            'label' => (string)($best[$nameCol] ?? $driverName),
            'source' => 'mapping_drivers fuzzy',
            'row' => $best,
            'score' => $bestScore,
        ];
    }

    /** @return array<string,mixed>|null */
    private function fetchExactDriverRow(string $driverName, string $idCol, string $nameCol): ?array
    {
        $driverName = trim($driverName);
        if ($driverName === '') {
            return null;
        }
        $select = $this->selectList('mapping_drivers', [$idCol, $nameCol, 'external_driver_id', 'edxeix_lessor_id', 'lessor_id', 'company_id', 'edxeix_company_id', 'is_active', 'active_vehicle_plate', 'driver_identifier', 'individual_identifier']);
        if ($select === '') {
            return null;
        }

        $sql = 'SELECT ' . $select . ' FROM `mapping_drivers` WHERE `' . $nameCol . '` = ? AND `' . $idCol . '` IS NOT NULL ORDER BY CASE WHEN `is_active` = 1 THEN 0 ELSE 1 END, id DESC LIMIT 1';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('s', $driverName);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (is_array($row) && trim((string)($row[$idCol] ?? '')) !== '' && trim((string)($row[$idCol] ?? '')) !== '0') {
                return $row;
            }
        } catch (Throwable) {
        }

        // Case-insensitive normalized fallback directly in PHP, still read-only.
        $target = $this->nameNorm($driverName);
        $rows = $this->fetchRows('mapping_drivers', [$idCol, $nameCol, 'external_driver_id', 'edxeix_lessor_id', 'lessor_id', 'company_id', 'edxeix_company_id', 'is_active', 'active_vehicle_plate', 'driver_identifier', 'individual_identifier'], $idCol . ' IS NOT NULL');
        foreach ($rows as $row) {
            if (isset($row['is_active']) && (string)$row['is_active'] === '0') {
                continue;
            }
            if ($this->nameNorm((string)($row[$nameCol] ?? '')) === $target) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array{id:string,label:string,source:string,row:array<string,mixed>|null,score:int}
     */
    private function lookupVehicle(string $plate): array
    {
        $empty = ['id' => '', 'label' => '', 'source' => 'none', 'row' => null, 'score' => 0];
        if ($plate === '' || !$this->tableExists('mapping_vehicles')) {
            return $empty;
        }

        $cols = $this->columns('mapping_vehicles');
        $idCol = $this->firstColumn($cols, ['edxeix_vehicle_id', 'vehicle_edxeix_id', 'edxeix_id']);
        $plateCol = $this->firstColumn($cols, ['plate', 'vehicle_plate', 'registration', 'license_plate', 'licence_plate']);
        if ($idCol === '' || $plateCol === '') {
            return $empty;
        }

        $exact = $this->fetchExactVehicleRow($plate, $idCol, $plateCol);
        if ($exact !== null) {
            return [
                'id' => trim((string)($exact[$idCol] ?? '')),
                'label' => (string)($exact[$plateCol] ?? $plate),
                'source' => 'mapping_vehicles exact',
                'row' => $exact,
                'score' => 150,
            ];
        }

        $rows = $this->fetchRows('mapping_vehicles', [$idCol, $plateCol, 'external_vehicle_id', 'external_vehicle_name', 'vehicle_model', 'edxeix_lessor_id', 'lessor_id', 'company_id', 'edxeix_company_id', 'is_active'], $idCol . ' IS NOT NULL');
        $target = $this->plateNorm($plate);
        $best = null;
        $bestScore = 0;
        foreach ($rows as $row) {
            if (isset($row['is_active']) && (string)$row['is_active'] === '0') {
                continue;
            }
            if (trim((string)($row[$idCol] ?? '')) === '' || trim((string)($row[$idCol] ?? '')) === '0') {
                continue;
            }
            $candidate = $this->plateNorm((string)($row[$plateCol] ?? ''));
            $score = 0;
            if ($candidate !== '' && $candidate === $target) {
                $score = 120;
            } elseif ($candidate !== '' && ($this->contains($candidate, $target) || $this->contains($target, $candidate))) {
                $score = 80;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        if ($best === null || $bestScore < 70) {
            return $empty;
        }

        return [
            'id' => trim((string)($best[$idCol] ?? '')),
            'label' => (string)($best[$plateCol] ?? $plate),
            'source' => 'mapping_vehicles fuzzy',
            'row' => $best,
            'score' => $bestScore,
        ];
    }

    /** @return array<string,mixed>|null */
    private function fetchExactVehicleRow(string $plate, string $idCol, string $plateCol): ?array
    {
        $target = $this->plateNorm($plate);
        if ($target === '') {
            return null;
        }
        $rows = $this->fetchRows('mapping_vehicles', [$idCol, $plateCol, 'external_vehicle_id', 'external_vehicle_name', 'vehicle_model', 'edxeix_lessor_id', 'lessor_id', 'company_id', 'edxeix_company_id', 'is_active'], $idCol . ' IS NOT NULL');
        foreach ($rows as $row) {
            if (isset($row['is_active']) && (string)$row['is_active'] === '0') {
                continue;
            }
            if (trim((string)($row[$idCol] ?? '')) === '' || trim((string)($row[$idCol] ?? '')) === '0') {
                continue;
            }
            if ($this->plateNorm((string)($row[$plateCol] ?? '')) === $target) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array{id:string,label:string,source:string}
     */
    private function lookupStartingPoint(): array
    {
        if (!$this->tableExists('mapping_starting_points')) {
            return ['id' => '', 'label' => '', 'source' => 'none'];
        }

        try {
            $sql = "SELECT label, edxeix_starting_point_id, internal_key FROM mapping_starting_points WHERE is_active = 1 ORDER BY CASE WHEN internal_key IN ('default','hq','home','edra','base','edra_mas') THEN 0 ELSE 1 END, CASE WHEN internal_key = 'default' THEN 0 ELSE 1 END, id ASC LIMIT 1";
            $result = $this->db->query($sql);
            $row = $result ? $result->fetch_assoc() : null;
            if (is_array($row) && trim((string)($row['edxeix_starting_point_id'] ?? '')) !== '') {
                return [
                    'id' => (string)$row['edxeix_starting_point_id'],
                    'label' => (string)($row['label'] ?? $row['internal_key'] ?? 'starting point'),
                    'source' => 'mapping_starting_points',
                ];
            }
        } catch (Throwable) {
        }

        return ['id' => '', 'label' => '', 'source' => 'none'];
    }

    /**
     * @param array<string,mixed> $driver
     * @param array<string,mixed> $vehicle
     * @return array{id:string,source:string}
     */
    private function resolveLessorId(string $operator, array $driver, array $vehicle): array
    {
        $driverLessor = $this->rowLessorId($driver['row'] ?? null);
        $vehicleLessor = $this->rowLessorId($vehicle['row'] ?? null);

        // Important production rule for partner/executing vehicles:
        // use the company where the selected driver/vehicle is registered in EDXEIX.
        if ($driverLessor !== '' && $vehicleLessor !== '' && $driverLessor === $vehicleLessor) {
            return ['id' => $driverLessor, 'source' => 'driver+vehicle DB lessor'];
        }
        if ($vehicleLessor !== '') {
            return ['id' => $vehicleLessor, 'source' => 'vehicle DB lessor'];
        }
        if ($driverLessor !== '') {
            return ['id' => $driverLessor, 'source' => 'driver DB lessor'];
        }

        $known = $this->knownLessorId($operator);
        if ($known !== '') {
            return ['id' => $known, 'source' => 'known operator alias'];
        }

        return ['id' => '', 'source' => 'not resolved'];
    }

    /** @param array<string,mixed>|null $row */
    private function rowLessorId(?array $row): string
    {
        if (!is_array($row)) {
            return '';
        }
        foreach (['edxeix_lessor_id', 'lessor_id', 'edxeix_company_id', 'company_id', 'operator_edxeix_id'] as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '' && trim((string)$row[$key]) !== '0') {
                return trim((string)$row[$key]);
            }
        }
        return '';
    }

    private function knownLessorId(string $operator): string
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
            'n g k μονοπροσωπη i k e' => '2124',
            'n g k' => '2124',
            'ngk' => '2124',
            'qualitative transfer mykonos ik e' => '2307',
            'qualitative transfer mykonos' => '2307',
            'mta' => '3894',
            'mykonos tourist agency ιδιωτικη κεφαλαιουχικη εταιρεια' => '3894',
            'mykonos tourist agency' => '3894',
            'vip road mykonos ιδιωτικη κεφαλαιουχικη εταιρεια' => '1487',
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
            if ($key !== '' && ($this->contains($key, $name) || $this->contains($name, $key))) {
                return $id;
            }
        }
        return '';
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE ?');
            $stmt->bind_param('s', $table);
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<int,string> */
    private function columns(string $table): array
    {
        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }
        $cols = [];
        try {
            $result = $this->db->query('SHOW COLUMNS FROM `' . $this->db->real_escape_string($table) . '`');
            while ($row = $result->fetch_assoc()) {
                $cols[] = (string)$row['Field'];
            }
        } catch (Throwable) {
        }
        $this->columnsCache[$table] = $cols;
        return $cols;
    }

    /** @param array<int,string> $columns @param array<int,string> $candidates */
    private function firstColumn(array $columns, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return '';
    }

    /** @param array<int,string> $preferredColumns */
    private function selectList(string $table, array $preferredColumns): string
    {
        $columns = $this->columns($table);
        $select = [];
        foreach ($preferredColumns as $col) {
            if (in_array($col, $columns, true)) {
                $select[] = '`' . $col . '`';
            }
        }
        if (in_array('id', $columns, true)) {
            $select[] = '`id`';
        }
        return implode(', ', array_unique($select));
    }

    /**
     * @param array<int,string> $preferredColumns
     * @return array<int,array<string,mixed>>
     */
    private function fetchRows(string $table, array $preferredColumns, string $where): array
    {
        $select = $this->selectList($table, $preferredColumns);
        if ($select === '') {
            return [];
        }
        try {
            $sql = 'SELECT ' . $select . ' FROM `' . $table . '` WHERE ' . $where . ' ORDER BY id DESC LIMIT 2000';
            $result = $this->db->query($sql);
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    private function nameScore(string $a, string $b): int
    {
        $a = $this->nameNorm($a);
        $b = $this->nameNorm($b);
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 120;
        }
        if ($this->contains($a, $b) || $this->contains($b, $a)) {
            return 90;
        }

        $aParts = array_values(array_filter(explode(' ', $a)));
        $bParts = array_values(array_filter(explode(' ', $b)));
        $hits = 0;
        foreach ($aParts as $part) {
            if (strlen($part) < 3) {
                continue;
            }
            foreach ($bParts as $other) {
                if ($part === $other || $this->contains($part, $other) || $this->contains($other, $part)) {
                    $hits++;
                    break;
                }
            }
        }
        return $hits * 30;
    }

    private function nameNorm(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = str_replace(['||', '|', '.', ',', ';', ':', '-', '_', '/', '\\', '(', ')', '"', "'"], ' ', $value);
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
        $plate = trim($plate);
        if (function_exists('mb_strtoupper')) {
            $plate = mb_strtoupper($plate, 'UTF-8');
        } else {
            $plate = strtoupper($plate);
        }
        $plate = strtr($plate, [
            'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M',
            'Ν' => 'N', 'Ο' => 'O', 'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
        ]);
        return preg_replace('/[^A-Z0-9]/', '', $plate) ?? $plate;
    }

    private function contains(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }
        return str_contains($haystack, $needle);
    }
}
