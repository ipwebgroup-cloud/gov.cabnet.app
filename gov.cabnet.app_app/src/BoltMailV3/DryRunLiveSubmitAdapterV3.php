<?php

declare(strict_types=1);

namespace Bridge\BoltMailV3;

final class DryRunLiveSubmitAdapterV3 implements LiveSubmitAdapterV3
{
    public function name(): string
    {
        return 'dry_run';
    }

    public function isLiveCapable(): bool
    {
        return false;
    }

    public function submit(array $edxeixPayload, array $context = []): array
    {
        $json = json_encode($edxeixPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $missing = [];
        foreach (['lessor', 'lessee_name', 'driver', 'vehicle', 'starting_point_id', 'boarding_point', 'disembark_point', 'started_at', 'ended_at', 'price'] as $key) {
            if (!array_key_exists($key, $edxeixPayload) || trim((string)$edxeixPayload[$key]) === '') {
                $missing[] = $key;
            }
        }

        return [
            'ok' => $missing === [],
            'submitted' => false,
            'dry_run' => true,
            'blocked' => $missing !== [],
            'adapter' => $this->name(),
            'message' => $missing === []
                ? 'V3 dry-run adapter accepted the final field package. No EDXEIX call was made.'
                : 'V3 dry-run adapter found missing final fields. No EDXEIX call was made.',
            'missing_fields' => $missing,
            'payload_sha256' => hash('sha256', $json),
            'payload_field_count' => count($edxeixPayload),
            'context' => [
                'queue_id' => isset($context['queue_id']) ? (string)$context['queue_id'] : '',
                'dedupe_key' => isset($context['dedupe_key']) ? (string)$context['dedupe_key'] : '',
                'lessor_id' => isset($context['lessor_id']) ? (string)$context['lessor_id'] : '',
                'vehicle_plate' => isset($context['vehicle_plate']) ? (string)$context['vehicle_plate'] : '',
            ],
        ];
    }
}
