<?php

namespace Bridge\Domain;

use Bridge\Repository\MappingRepository;

final class MappingService
{
    public function __construct(private readonly MappingRepository $mappings)
    {
    }

    public function resolve(array $booking): array
    {
        $errors = [];

        $driver = $this->mappings->findDriver(
            (string) ($booking['driver_external_id'] ?? ''),
            (string) ($booking['driver_name'] ?? '')
        );

        if (!$driver) {
            $errors[] = 'driver';
        }

        $vehicle = $this->mappings->findVehicle(
            (string) ($booking['vehicle_external_id'] ?? ''),
            (string) ($booking['vehicle_plate'] ?? '')
        );

        if (!$vehicle) {
            $errors[] = 'vehicle';
        }

        $startingPoint = null;
        if (!empty($booking['starting_point_key'])) {
            $startingPoint = $this->mappings->findStartingPoint((string) $booking['starting_point_key']);
        }

        if (!$startingPoint) {
            $errors[] = 'starting_point';
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'driver' => $driver,
            'vehicle' => $vehicle,
            'starting_point' => $startingPoint,
        ];
    }
}
