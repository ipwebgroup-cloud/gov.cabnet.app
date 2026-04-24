<?php

namespace Bridge\Bolt;

use Bridge\Config;
use Bridge\Logger;
use Bridge\Repository\RawPayloadRepository;
use Bridge\Repository\BookingRepository;
use Bridge\Domain\BookingNormalizer;

final class BoltSyncService
{
    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly BoltApiClient $api,
        private readonly RawPayloadRepository $rawPayloads,
        private readonly BookingRepository $bookings,
        private readonly BookingNormalizer $normalizer
    ) {
    }

    public function sync(): array
    {
        $endpoint = $this->config->get('bolt.trips_endpoint');
        if (!$endpoint) {
            throw new \RuntimeException('Missing bolt.trips_endpoint in config.');
        }

        $payload = $this->api->getJson($endpoint, ['limit' => 100]);

        $items = $payload['items'] ?? $payload['data'] ?? $payload;
        if (!is_array($items)) {
            throw new \RuntimeException('Bolt sync payload did not contain an iterable list.');
        }

        $created = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sourceId = (string) ($item['id'] ?? $item['trip_id'] ?? uniqid('bolt_', true));
            $this->rawPayloads->create('bolt_trip', $sourceId, $item);

            $normalized = $this->normalizer->fromBoltPayload($item);
            if (!$normalized) {
                continue;
            }

            if ($this->bookings->findByDedupeHash($normalized['dedupe_hash'])) {
                continue;
            }

            $this->bookings->create($normalized);
            $created++;
        }

        $this->logger->info('Bolt sync finished', ['created' => $created]);

        return ['created' => $created, 'received' => count($items)];
    }
}
