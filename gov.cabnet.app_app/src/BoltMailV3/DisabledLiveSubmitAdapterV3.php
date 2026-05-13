<?php

declare(strict_types=1);

namespace Bridge\BoltMailV3;

final class DisabledLiveSubmitAdapterV3 implements LiveSubmitAdapterV3
{
    public function name(): string
    {
        return 'disabled';
    }

    public function isLiveCapable(): bool
    {
        return false;
    }

    public function submit(array $edxeixPayload, array $context = []): array
    {
        return [
            'ok' => false,
            'submitted' => false,
            'blocked' => true,
            'adapter' => $this->name(),
            'reason' => 'adapter_disabled',
            'message' => 'V3 live submit adapter is disabled. No EDXEIX call was made.',
            'payload_sha256' => hash('sha256', json_encode($edxeixPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            'context' => $this->safeContext($context),
        ];
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function safeContext(array $context): array
    {
        return [
            'queue_id' => isset($context['queue_id']) ? (string)$context['queue_id'] : '',
            'dedupe_key' => isset($context['dedupe_key']) ? (string)$context['dedupe_key'] : '',
            'lessor_id' => isset($context['lessor_id']) ? (string)$context['lessor_id'] : '',
            'vehicle_plate' => isset($context['vehicle_plate']) ? (string)$context['vehicle_plate'] : '',
        ];
    }
}
