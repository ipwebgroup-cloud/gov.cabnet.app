<?php

declare(strict_types=1);

namespace Bridge\BoltMailV3;

/**
 * Closed-gate skeleton for the future real EDXEIX V3 live-submit adapter.
 *
 * This class intentionally DOES NOT call EDXEIX.
 * It exists only so diagnostics and future wiring can see the intended adapter
 * location without enabling live submission or introducing browser/session work.
 *
 * To convert this into a real adapter later, a separate explicitly approved
 * live-submit patch must implement the EDXEIX call path and change
 * isLiveCapable() only after the master gate, operator approval, future-safe
 * checks, and payload audit have all been proven on a real eligible trip.
 */
final class EdxeixLiveSubmitAdapterV3 implements LiveSubmitAdapterV3
{
    public function name(): string
    {
        return 'edxeix_live_skeleton';
    }

    /**
     * The skeleton is deliberately not live-capable.
     */
    public function isLiveCapable(): bool
    {
        return false;
    }

    /**
     * @param array<string,mixed> $edxeixPayload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function submit(array $edxeixPayload, array $context = []): array
    {
        $json = json_encode($edxeixPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        return [
            'ok' => false,
            'submitted' => false,
            'blocked' => true,
            'adapter' => $this->name(),
            'live_capable' => false,
            'reason' => 'edxeix_live_adapter_skeleton_not_implemented',
            'message' => 'V3 EDXEIX live adapter skeleton is present, but real EDXEIX submission is not implemented or enabled. No EDXEIX call was made.',
            'payload_sha256' => hash('sha256', $json),
            'payload_field_count' => count($edxeixPayload),
            'missing_fields' => $this->missingRequiredFields($edxeixPayload),
            'context' => $this->safeContext($context),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,string>
     */
    private function missingRequiredFields(array $payload): array
    {
        $missing = [];
        foreach ([
            'lessor',
            'lessee_name',
            'driver',
            'vehicle',
            'starting_point_id',
            'boarding_point',
            'disembark_point',
            'started_at',
            'ended_at',
            'price',
        ] as $key) {
            if (!array_key_exists($key, $payload) || trim((string)$payload[$key]) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,string>
     */
    private function safeContext(array $context): array
    {
        return [
            'queue_id' => isset($context['queue_id']) ? (string)$context['queue_id'] : '',
            'dedupe_key' => isset($context['dedupe_key']) ? (string)$context['dedupe_key'] : '',
            'lessor_id' => isset($context['lessor_id']) ? (string)$context['lessor_id'] : '',
            'vehicle_plate' => isset($context['vehicle_plate']) ? (string)$context['vehicle_plate'] : '',
            'pickup_datetime' => isset($context['pickup_datetime']) ? (string)$context['pickup_datetime'] : '',
        ];
    }
}
