<?php

namespace Bridge\Domain;

use Bridge\Logger;
use Bridge\Repository\BookingRepository;
use Bridge\Repository\JobRepository;
use Bridge\Repository\AttemptRepository;
use Bridge\Edxeix\EdxeixFormReader;
use Bridge\Edxeix\EdxeixPayloadBuilder;
use Bridge\Edxeix\EdxeixSubmitter;

final class SubmissionService
{
    public function __construct(
        private readonly Logger $logger,
        private readonly BookingRepository $bookings,
        private readonly JobRepository $jobs,
        private readonly AttemptRepository $attempts,
        private readonly MappingService $mappingService,
        private readonly EdxeixFormReader $formReader,
        private readonly EdxeixPayloadBuilder $payloadBuilder,
        private readonly EdxeixSubmitter $submitter
    ) {
    }

    public function processNext(string $workerName = 'worker'): ?array
    {
        $job = $this->jobs->claimNext($workerName);
        if (!$job) {
            return null;
        }

        $booking = $this->bookings->find((int) $job['normalized_booking_id']);
        if (!$booking) {
            $this->jobs->markFailed((int) $job['id'], 'failed_validation', 'Booking not found.');
            return ['job_id' => (int) $job['id'], 'success' => false, 'error' => 'Booking not found'];
        }

        $mapping = $this->mappingService->resolve($booking);
        if (!$mapping['ok']) {
            $message = 'Missing mapping: ' . implode(', ', $mapping['errors']);
            $this->jobs->markFailed((int) $job['id'], 'awaiting_mapping', $message);
            return ['job_id' => (int) $job['id'], 'success' => false, 'error' => $message];
        }

        try {
            $formState = $this->formReader->fetchCreateFormState();
            $payload = $this->payloadBuilder->build($booking, $mapping, $formState);
            $result = $this->submitter->submit($payload);

            $this->attempts->create([
                'submission_job_id' => (int) $job['id'],
                'request_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_status' => $result['status'] ?? 0,
                'response_headers_json' => json_encode($result['headers'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_body' => (string) ($result['body'] ?? ''),
                'success' => (bool) ($result['success'] ?? false),
                'remote_reference' => $result['remote_reference'] ?? null,
            ]);

            if ($result['success']) {
                $this->jobs->markSubmitted((int) $job['id']);
            } else {
                $this->jobs->markFailed((int) $job['id'], 'failed_validation', 'edxeix submission did not confirm success.');
            }

            return [
                'job_id' => (int) $job['id'],
                'success' => (bool) ($result['success'] ?? false),
                'mode' => $result['mode'] ?? 'unknown',
                'remote_reference' => $result['remote_reference'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Submission failed', ['job_id' => (int) $job['id'], 'error' => $e->getMessage()]);
            $this->jobs->markFailed((int) $job['id'], 'failed_transport', $e->getMessage());

            return [
                'job_id' => (int) $job['id'],
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
