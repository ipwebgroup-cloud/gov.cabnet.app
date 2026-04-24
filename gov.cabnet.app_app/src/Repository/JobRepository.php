<?php

namespace Bridge\Repository;

use Bridge\Database;

final class JobRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function queue(int $bookingId, string $targetSystem = 'edxeix'): int
    {
        return $this->db->insert(
            'INSERT INTO submission_jobs
            (normalized_booking_id, target_system, status, priority, available_at, created_at, updated_at)
            VALUES (?, ?, "pending", 100, NOW(), NOW(), NOW())',
            [$bookingId, $targetSystem]
        );
    }

    public function claimNext(string $workerName = 'worker'): ?array
    {
        $this->db->beginTransaction();

        try {
            $job = $this->db->fetchOne(
                'SELECT * FROM submission_jobs
                 WHERE status = "pending" AND available_at <= NOW()
                 ORDER BY priority ASC, id ASC
                 LIMIT 1 FOR UPDATE'
            );

            if (!$job) {
                $this->db->commit();
                return null;
            }

            $this->db->execute(
                'UPDATE submission_jobs
                 SET status = "processing", locked_at = NOW(), locked_by = ?, updated_at = NOW()
                 WHERE id = ?',
                [$workerName, $job['id']]
            );

            $this->db->commit();
            return $this->db->fetchOne('SELECT * FROM submission_jobs WHERE id = ?', [$job['id']]);
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function markSubmitted(int $jobId): void
    {
        $this->db->execute(
            'UPDATE submission_jobs SET status = "submitted", updated_at = NOW() WHERE id = ?',
            [$jobId]
        );
    }

    public function markFailed(int $jobId, string $status, string $message): void
    {
        $this->db->execute(
            'UPDATE submission_jobs
             SET status = ?, last_error = ?, retry_count = retry_count + 1, updated_at = NOW()
             WHERE id = ?',
            [$status, $message, $jobId]
        );
    }

    public function list(string $status = ''): array
    {
        if ($status !== '') {
            return $this->db->fetchAll(
                'SELECT j.*, b.customer_name, b.started_at
                 FROM submission_jobs j
                 JOIN normalized_bookings b ON b.id = j.normalized_booking_id
                 WHERE j.status = ?
                 ORDER BY j.id DESC',
                [$status]
            );
        }

        return $this->db->fetchAll(
            'SELECT j.*, b.customer_name, b.started_at
             FROM submission_jobs j
             JOIN normalized_bookings b ON b.id = j.normalized_booking_id
             ORDER BY j.id DESC
             LIMIT 200'
        );
    }
}
