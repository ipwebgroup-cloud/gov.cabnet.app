<?php

namespace Bridge\Edxeix;

use Bridge\Config;

final class EdxeixPayloadBuilder
{
    public function __construct(private readonly Config $config)
    {
    }

    public function build(array $booking, array $mapping, array $formState): array
    {
        $customerType = $booking['customer_type'] === 'legal' ? 'legal' : 'natural';
        $startingPointId = (string) $mapping['starting_point']['edxeix_starting_point_id'];

        return [
            '_token' => $formState['csrf_token'],
            'broker' => $formState['broker'] !== '' ? $formState['broker'] : (string) ($this->config->get('edxeix.default_broker', '')),
            'lessor' => (string) ($formState['lessor'] ?: $this->config->get('edxeix.lessor_id')),
            'lessee[type]' => $customerType,
            'lessee[name]' => (string) $booking['customer_name'],
            'lessee[vat_number]' => $customerType === 'legal' ? (string) ($booking['customer_vat_number'] ?? '') : '',
            'lessee[legal_representative]' => $customerType === 'legal' ? (string) ($booking['customer_representative'] ?? '') : '',
            'driver' => (string) $mapping['driver']['edxeix_driver_id'],
            'vehicle' => (string) $mapping['vehicle']['edxeix_vehicle_id'],

            // EDXEIX currently posts this select under name="starting_point".
            // Keep starting_point_id as a backwards-compatible alias because older bridge
            // payloads and preview tools used that key.
            'starting_point' => $startingPointId,
            'starting_point_id' => $startingPointId,

            'boarding_point' => (string) $booking['boarding_point'],
            'coordinates' => (string) ($booking['coordinates'] ?? ''),
            'disembark_point' => (string) $booking['disembark_point'],
            'drafted_at' => $this->formatDateTime($booking['drafted_at']),
            'started_at' => $this->formatDateTime($booking['started_at']),
            'ended_at' => $this->formatDateTime($booking['ended_at']),
            'price' => number_format((float) $booking['price'], 2, '.', ''),
        ];
    }

    private function formatDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('d/m/Y H:i', $timestamp);
    }
}
