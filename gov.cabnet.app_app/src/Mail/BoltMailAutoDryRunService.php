<?php

namespace Bridge\Mail;

use Bridge\Config;
use Bridge\Database;
use RuntimeException;
use Throwable;

final class BoltMailAutoDryRunService
{
    private BoltMailIntakeBookingBridge $bookingBridge;
    private BoltMailDryRunEvidenceService $evidenceService;

    public function __construct(
        private readonly Database $db,
        private readonly Config $config
    ) {
        $this->bookingBridge = new BoltMailIntakeBookingBridge($db, $config);
        $this->evidenceService = new BoltMailDryRunEvidenceService($db->connection(), $config->all());
    }

    public function run(int $limit = 25, bool $previewOnly = false, string $createdBy = 'auto-cron'): array
    {
        $limit = max(1, min(200, $limit));
        $startedAt = date('c');

        $summary = [
            'candidate_rows' => 0,
            'preview_only' => $previewOnly,
            'created_bookings' => 0,
            'linked_existing_bookings' => 0,
            'evidence_recorded' => 0,
            'evidence_existing' => 0,
            'blocked' => 0,
            'errors' => 0,
        ];
        $items = [];
        $warnings = [];

        $liveSubmitEnabled = (bool)$this->config->get('edxeix.live_submit_enabled', false);
        $appDryRun = (bool)$this->config->get('app.dry_run', true);

        if ($liveSubmitEnabled) {
            throw new RuntimeException('Auto dry-run evidence is disabled while edxeix.live_submit_enabled is true.');
        }
        if (!$appDryRun) {
            throw new RuntimeException('Auto dry-run evidence is disabled while app.dry_run is false.');
        }
        if (!$this->tableExists('bolt_mail_dry_run_evidence')) {
            throw new RuntimeException('Missing table bolt_mail_dry_run_evidence. Install v4.2 SQL before enabling this worker.');
        }

        $candidates = $this->bookingBridge->listFutureCandidates($limit);
        $summary['candidate_rows'] = count($candidates);

        foreach ($candidates as $candidate) {
            $intakeId = (int)($candidate['id'] ?? 0);
            $item = [
                'intake_id' => $intakeId,
                'customer_name' => (string)($candidate['customer_name'] ?? ''),
                'driver_name' => (string)($candidate['driver_name'] ?? ''),
                'vehicle_plate' => (string)($candidate['vehicle_plate'] ?? ''),
                'parsed_pickup_at' => (string)($candidate['parsed_pickup_at'] ?? ''),
                'booking_id' => null,
                'evidence_id' => null,
                'status' => 'pending',
                'message' => '',
                'blockers' => [],
            ];

            try {
                $bookingId = (int)($candidate['linked_booking_id'] ?? 0);

                if ($bookingId <= 0) {
                    $preview = $this->bookingBridge->previewById($intakeId);
                    if (empty($preview['create_allowed'])) {
                        $summary['blocked']++;
                        $item['status'] = 'blocked_preview';
                        $item['message'] = 'Candidate is not safe to create as a local booking.';
                        $item['blockers'] = $preview['errors'] ?? [];
                        $items[] = $item;
                        continue;
                    }

                    if ($previewOnly) {
                        $item['status'] = 'preview_booking_ready';
                        $item['message'] = 'Local preflight booking would be created.';
                        $items[] = $item;
                        continue;
                    }

                    $createResult = $this->bookingBridge->createLocalPreflightBooking($intakeId);
                    if (empty($createResult['ok']) || empty($createResult['booking_id'])) {
                        $summary['blocked']++;
                        $item['status'] = 'blocked_create_booking';
                        $item['message'] = (string)($createResult['message'] ?? 'Booking creation failed.');
                        $items[] = $item;
                        continue;
                    }

                    $bookingId = (int)$createResult['booking_id'];
                    $item['booking_id'] = $bookingId;
                    if (!empty($createResult['created'])) {
                        $summary['created_bookings']++;
                    } else {
                        $summary['linked_existing_bookings']++;
                    }
                } else {
                    $item['booking_id'] = $bookingId;
                    $summary['linked_existing_bookings']++;
                }

                $existingEvidence = $this->findEvidenceForBooking($bookingId);
                if ($existingEvidence) {
                    $summary['evidence_existing']++;
                    $item['evidence_id'] = (int)$existingEvidence['id'];
                    $item['status'] = 'evidence_exists';
                    $item['message'] = 'Dry-run evidence already exists for this local booking.';
                    $items[] = $item;
                    continue;
                }

                $evidencePreview = $this->evidenceService->buildEvidencePreview($bookingId);
                if (empty($evidencePreview['can_record'])) {
                    $summary['blocked']++;
                    $item['status'] = 'blocked_evidence';
                    $item['message'] = 'Dry-run evidence is blocked by safety checks.';
                    $item['blockers'] = $evidencePreview['safety']['blockers'] ?? [];
                    $items[] = $item;
                    continue;
                }

                if ($previewOnly) {
                    $item['status'] = 'preview_evidence_ready';
                    $item['message'] = 'Dry-run evidence would be recorded.';
                    $items[] = $item;
                    continue;
                }

                $evidenceId = $this->evidenceService->recordEvidence($bookingId, $createdBy);
                $summary['evidence_recorded']++;
                $item['evidence_id'] = $evidenceId;
                $item['status'] = 'evidence_recorded';
                $item['message'] = 'Local booking and dry-run evidence are ready. No submission job was created.';
                $items[] = $item;
            } catch (Throwable $e) {
                $summary['errors']++;
                $item['status'] = 'error';
                $item['message'] = $e->getMessage();
                $items[] = $item;
            }
        }

        $jobs = $this->safetyCounts();
        if ($jobs['submission_jobs_total'] > 0 || $jobs['open_submission_jobs'] > 0 || $jobs['submission_attempts_total'] > 0) {
            $warnings[] = 'Submission job/attempt rows exist. This worker did not create them, but the queue should be reviewed before live submit is considered.';
        }

        return [
            'ok' => $summary['errors'] === 0,
            'started_at' => $startedAt,
            'finished_at' => date('c'),
            'summary' => $summary,
            'items' => $items,
            'warnings' => $warnings,
            'safety' => [
                'app_dry_run' => $appDryRun,
                'live_submit_enabled' => $liveSubmitEnabled,
                'future_guard_minutes' => (int)$this->config->get('edxeix.future_start_guard_minutes', 2),
                'no_bolt_call' => true,
                'no_edxeix_call' => true,
                'no_submission_jobs_created' => true,
                'no_live_submit' => true,
            ] + $jobs,
        ];
    }

    public function status(): array
    {
        $intake = $this->fetchOne("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN parse_status='parsed' AND safety_status='future_candidate' AND linked_booking_id IS NULL THEN 1 ELSE 0 END) AS active_unlinked_candidates,
            SUM(CASE WHEN parse_status='parsed' AND safety_status='future_candidate' AND linked_booking_id IS NOT NULL THEN 1 ELSE 0 END) AS linked_future_rows,
            SUM(CASE WHEN customer_name LIKE 'CABNET TEST%' THEN 1 ELSE 0 END) AS synthetic_rows
            FROM bolt_mail_intake") ?: [];

        $bookings = $this->fetchOne("SELECT COUNT(*) AS total FROM normalized_bookings WHERE source='bolt_mail'") ?: [];
        $evidence = $this->tableExists('bolt_mail_dry_run_evidence')
            ? ($this->fetchOne("SELECT COUNT(*) AS total FROM bolt_mail_dry_run_evidence") ?: [])
            : ['total' => 0];

        return [
            'total_intake_rows' => (int)($intake['total'] ?? 0),
            'active_unlinked_candidates' => (int)($intake['active_unlinked_candidates'] ?? 0),
            'linked_future_rows' => (int)($intake['linked_future_rows'] ?? 0),
            'synthetic_rows' => (int)($intake['synthetic_rows'] ?? 0),
            'mail_created_bookings' => (int)($bookings['total'] ?? 0),
            'dry_run_evidence_rows' => (int)($evidence['total'] ?? 0),
        ] + $this->safetyCounts();
    }

    public function recentEvidence(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        if (!$this->tableExists('bolt_mail_dry_run_evidence')) {
            return [];
        }
        return $this->fetchAll("SELECT e.*, b.customer_name, b.driver_name, b.vehicle_plate, b.started_at
            FROM bolt_mail_dry_run_evidence e
            LEFT JOIN normalized_bookings b ON b.id = e.normalized_booking_id
            ORDER BY e.id DESC
            LIMIT " . $limit);
    }

    private function findEvidenceForBooking(int $bookingId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM bolt_mail_dry_run_evidence WHERE normalized_booking_id = ? ORDER BY id DESC LIMIT 1',
            [$bookingId],
            'i'
        );
    }

    private function safetyCounts(): array
    {
        $jobs = $this->fetchOne("SELECT COUNT(*) AS total, SUM(CASE WHEN status IN ('pending','processing') THEN 1 ELSE 0 END) AS open_count FROM submission_jobs") ?: [];
        $attempts = $this->fetchOne('SELECT COUNT(*) AS total FROM submission_attempts') ?: [];

        return [
            'submission_jobs_total' => (int)($jobs['total'] ?? 0),
            'open_submission_jobs' => (int)($jobs['open_count'] ?? 0),
            'submission_attempts_total' => (int)($attempts['total'] ?? 0),
        ];
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        return ((int)($row['c'] ?? 0)) > 0;
    }

    private function fetchOne(string $sql, array $params = [], string $types = ''): ?array
    {
        $rows = $this->fetchAll($sql, $params, $types);
        return $rows[0] ?? null;
    }

    private function fetchAll(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->db->connection()->prepare($sql);
        if ($params) {
            if ($types === '') {
                $types = str_repeat('s', count($params));
            }
            $refs = [$types];
            foreach ($params as $key => $value) {
                $refs[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    }
}
