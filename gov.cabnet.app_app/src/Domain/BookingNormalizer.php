<?php

namespace Bridge\Domain;

final class BookingNormalizer
{
    public function fromManualPayload(array $payload): array
    {
        $normalized = [
            'source' => 'manual',
            'source_trip_id' => $payload['source_trip_id'] ?? null,
            'source_booking_id' => $payload['source_booking_id'] ?? null,
            'status' => (string) ($payload['status'] ?? 'confirmed'),
            'customer_type' => (string) ($payload['customer_type'] ?? 'natural'),
            'customer_name' => (string) ($payload['customer_name'] ?? ''),
            'customer_vat_number' => $payload['customer_vat_number'] ?? null,
            'customer_representative' => $payload['customer_representative'] ?? null,
            'driver_external_id' => $payload['driver_external_id'] ?? null,
            'driver_name' => $payload['driver_name'] ?? null,
            'vehicle_external_id' => $payload['vehicle_external_id'] ?? null,
            'vehicle_plate' => $payload['vehicle_plate'] ?? null,
            'starting_point_key' => $payload['starting_point_key'] ?? null,
            'boarding_point' => (string) ($payload['boarding_point'] ?? ''),
            'coordinates' => $payload['coordinates'] ?? null,
            'disembark_point' => (string) ($payload['disembark_point'] ?? ''),
            'drafted_at' => (string) ($payload['drafted_at'] ?? date('Y-m-d H:i:s')),
            'started_at' => (string) ($payload['started_at'] ?? date('Y-m-d H:i:s')),
            'ended_at' => (string) ($payload['ended_at'] ?? date('Y-m-d H:i:s', strtotime('+30 minutes'))),
            'price' => (string) ($payload['price'] ?? '0.00'),
            'currency' => (string) ($payload['currency'] ?? 'EUR'),
            'broker_key' => $payload['broker_key'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];

        $normalized['dedupe_hash'] = $this->buildDedupeHash($normalized);

        return $normalized;
    }

    public function fromBoltPayload(array $payload): ?array
    {
        $mapped = [
            'source' => 'bolt',
            'source_trip_id' => $payload['id'] ?? $payload['trip_id'] ?? null,
            'source_booking_id' => $payload['booking_id'] ?? null,
            'status' => (string) ($payload['status'] ?? 'unknown'),
            'customer_type' => 'natural',
            'customer_name' => (string) ($payload['passenger_name'] ?? $payload['customer_name'] ?? ''),
            'customer_vat_number' => null,
            'customer_representative' => null,
            'driver_external_id' => $payload['driver']['id'] ?? $payload['driver_id'] ?? null,
            'driver_name' => $payload['driver']['name'] ?? $payload['driver_name'] ?? null,
            'vehicle_external_id' => $payload['vehicle']['id'] ?? $payload['vehicle_id'] ?? null,
            'vehicle_plate' => $payload['vehicle']['plate'] ?? $payload['plate'] ?? null,
            'starting_point_key' => $payload['starting_point_key'] ?? 'edra_mas',
            'boarding_point' => (string) ($payload['pickup_address'] ?? $payload['boarding_point'] ?? ''),
            'coordinates' => $payload['pickup_coordinates'] ?? null,
            'disembark_point' => (string) ($payload['dropoff_address'] ?? $payload['disembark_point'] ?? ''),
            'drafted_at' => (string) ($payload['created_at'] ?? date('Y-m-d H:i:s')),
            'started_at' => (string) ($payload['scheduled_start_at'] ?? $payload['started_at'] ?? date('Y-m-d H:i:s')),
            'ended_at' => (string) ($payload['scheduled_end_at'] ?? $payload['ended_at'] ?? date('Y-m-d H:i:s', strtotime('+30 minutes'))),
            'price' => (string) ($payload['price'] ?? '0.00'),
            'currency' => (string) ($payload['currency'] ?? 'EUR'),
            'broker_key' => $payload['broker_key'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];

        if ($mapped['customer_name'] === '' || $mapped['boarding_point'] === '' || $mapped['disembark_point'] === '') {
            return null;
        }

        $mapped['dedupe_hash'] = $this->buildDedupeHash($mapped);

        return $mapped;
    }

    private function buildDedupeHash(array $data): string
    {
        return hash('sha256', implode('|', [
            $data['source'],
            $data['source_trip_id'] ?? '',
            $data['source_booking_id'] ?? '',
            $data['customer_name'] ?? '',
            $data['started_at'] ?? '',
            $data['boarding_point'] ?? '',
            $data['disembark_point'] ?? '',
            $data['price'] ?? '',
        ]));
    }
}
