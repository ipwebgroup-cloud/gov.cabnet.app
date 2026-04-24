<?php

namespace Bridge\Repository;

use Bridge\Database;

final class BookingRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO normalized_bookings
            (source, source_trip_id, source_booking_id, status, customer_type, customer_name, customer_vat_number,
             customer_representative, driver_external_id, driver_name, vehicle_external_id, vehicle_plate,
             starting_point_key, boarding_point, coordinates, disembark_point, drafted_at, started_at, ended_at,
             price, currency, broker_key, notes, dedupe_hash, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $data['source'],
                $data['source_trip_id'] ?? null,
                $data['source_booking_id'] ?? null,
                $data['status'],
                $data['customer_type'],
                $data['customer_name'],
                $data['customer_vat_number'] ?? null,
                $data['customer_representative'] ?? null,
                $data['driver_external_id'] ?? null,
                $data['driver_name'] ?? null,
                $data['vehicle_external_id'] ?? null,
                $data['vehicle_plate'] ?? null,
                $data['starting_point_key'] ?? null,
                $data['boarding_point'],
                $data['coordinates'] ?? null,
                $data['disembark_point'],
                $data['drafted_at'],
                $data['started_at'],
                $data['ended_at'],
                $data['price'],
                $data['currency'] ?? 'EUR',
                $data['broker_key'] ?? null,
                $data['notes'] ?? null,
                $data['dedupe_hash'],
            ]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM normalized_bookings WHERE id = ?', [$id]);
    }

    public function listRecent(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM normalized_bookings ORDER BY id DESC LIMIT ' . (int) $limit
        );
    }

    public function findByDedupeHash(string $hash): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM normalized_bookings WHERE dedupe_hash = ? LIMIT 1',
            [$hash]
        );
    }
}
