<?php

namespace Bridge\Repository;

use Bridge\Database;

final class MappingRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findDriver(string $externalDriverId = '', string $driverName = ''): ?array
    {
        if ($externalDriverId !== '') {
            $row = $this->db->fetchOne(
                'SELECT * FROM mapping_drivers WHERE external_driver_id = ? AND is_active = 1 LIMIT 1',
                [$externalDriverId]
            );
            if ($row) {
                return $row;
            }
        }

        if ($driverName !== '') {
            return $this->db->fetchOne(
                'SELECT * FROM mapping_drivers WHERE external_driver_name = ? AND is_active = 1 LIMIT 1',
                [$driverName]
            );
        }

        return null;
    }

    public function findVehicle(string $externalVehicleId = '', string $plate = ''): ?array
    {
        if ($externalVehicleId !== '') {
            $row = $this->db->fetchOne(
                'SELECT * FROM mapping_vehicles WHERE external_vehicle_id = ? AND is_active = 1 LIMIT 1',
                [$externalVehicleId]
            );
            if ($row) {
                return $row;
            }
        }

        if ($plate !== '') {
            return $this->db->fetchOne(
                'SELECT * FROM mapping_vehicles WHERE plate = ? AND is_active = 1 LIMIT 1',
                [$plate]
            );
        }

        return null;
    }

    public function findStartingPoint(string $internalKey): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM mapping_starting_points WHERE internal_key = ? AND is_active = 1 LIMIT 1',
            [$internalKey]
        );
    }
}
