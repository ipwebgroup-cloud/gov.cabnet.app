<?php

namespace Bridge\Repository;

use Bridge\Database;

final class RawPayloadRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(string $sourceType, string $sourceId, array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO bolt_raw_payloads (source_type, source_id, payload_json, fetched_at, created_at)
             VALUES (?, ?, ?, NOW(), NOW())',
            [$sourceType, $sourceId, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
    }
}
