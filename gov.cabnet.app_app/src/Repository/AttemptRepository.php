<?php

namespace Bridge\Repository;

use Bridge\Database;

final class AttemptRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO submission_attempts
             (submission_job_id, request_payload_json, response_status, response_headers_json, response_body,
              success, remote_reference, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $data['submission_job_id'],
                $data['request_payload_json'],
                $data['response_status'],
                $data['response_headers_json'],
                $data['response_body'],
                $data['success'] ? 1 : 0,
                $data['remote_reference'] ?? null,
            ]
        );
    }

    public function listByJob(int $jobId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM submission_attempts WHERE submission_job_id = ? ORDER BY id DESC',
            [$jobId]
        );
    }
}
