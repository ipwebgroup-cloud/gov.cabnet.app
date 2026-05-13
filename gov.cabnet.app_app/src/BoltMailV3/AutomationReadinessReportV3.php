<?php
declare(strict_types=1);

namespace Bridge\BoltMailV3;

use DateTimeImmutable;
use Throwable;
use mysqli;

final class AutomationReadinessReportV3
{
    public const VERSION = 'v3.0.32-automation-readiness-report';

    /** @var array<string,string> */
    private array $cronLogs = [
        'intake' => 'pre_ride_email_v3_cron.log',
        'starting_point_guard' => 'pre_ride_email_v3_starting_point_guard_cron.log',
        'submit_dry_run' => 'pre_ride_email_v3_submit_dry_run_cron.log',
        'live_readiness' => 'pre_ride_email_v3_live_submit_readiness_cron.log',
        'live_submit_scaffold' => 'pre_ride_email_v3_live_submit_cron.log',
    ];

    public function __construct(
        private readonly mysqli $db,
        private readonly string $appRoot
    ) {
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        $schema = $this->schemaStatus();
        $config = $this->liveSubmitConfigStatus();
        $queue = $this->queueStatus($schema);
        $cron = $this->cronStatus();
        $safety = $this->safetyStatus($config);

        $readyForV3Manual = $schema['queue']
            && $schema['events']
            && $schema['start_options']
            && $schema['approvals']
            && $cron['all_required_present']
            && $cron['critical_fresh'];

        $readyForFutureLive = $readyForV3Manual
            && (bool)$config['ok_for_future_live_submit']
            && (bool)$safety['hard_live_enabled'];

        return [
            'ok' => true,
            'version' => self::VERSION,
            'generated_at' => date(DATE_ATOM),
            'database' => $this->databaseName(),
            'schema' => $schema,
            'queue' => $queue,
            'cron' => $cron,
            'live_submit_config' => $config,
            'safety' => $safety,
            'readiness' => [
                'ready_for_v3_manual_handoff' => $readyForV3Manual,
                'ready_for_future_live_submit' => $readyForFutureLive,
                'expected_live_submit_state' => 'closed_until_explicitly_enabled',
                'current_next_action' => $this->nextAction($schema, $cron, $config, $queue),
            ],
        ];
    }

    /** @return array<string,bool> */
    public function schemaStatus(): array
    {
        return [
            'queue' => $this->tableExists('pre_ride_email_v3_queue'),
            'events' => $this->tableExists('pre_ride_email_v3_queue_events'),
            'start_options' => $this->tableExists('pre_ride_email_v3_starting_point_options'),
            'approvals' => $this->tableExists('pre_ride_email_v3_live_submit_approvals'),
        ];
    }

    /** @param array<string,bool> $schema @return array<string,mixed> */
    public function queueStatus(array $schema): array
    {
        if (empty($schema['queue'])) {
            return [
                'available' => false,
                'total' => 0,
                'active' => 0,
                'future_active' => 0,
                'submit_dry_run_ready' => 0,
                'live_submit_ready' => 0,
                'submitted' => 0,
                'blocked' => 0,
                'status_counts' => [],
                'recent_rows' => [],
            ];
        }

        $statusCounts = [];
        $res = $this->db->query(
            "SELECT queue_status, COUNT(*) AS total, " .
            "SUM(CASE WHEN pickup_datetime IS NOT NULL AND pickup_datetime > NOW() THEN 1 ELSE 0 END) AS future_total " .
            "FROM pre_ride_email_v3_queue GROUP BY queue_status ORDER BY total DESC, queue_status ASC"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $status = (string)($row['queue_status'] ?? '');
                $statusCounts[$status] = [
                    'total' => (int)($row['total'] ?? 0),
                    'future' => (int)($row['future_total'] ?? 0),
                ];
            }
        }

        $recentRows = [];
        $res = $this->db->query(
            "SELECT id, queue_status, customer_name, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, pickup_datetime, created_at " .
            "FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 10"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $recentRows[] = $row;
            }
        }

        return [
            'available' => true,
            'total' => $this->countSql('SELECT COUNT(*) FROM pre_ride_email_v3_queue'),
            'active' => $this->countSql("SELECT COUNT(*) FROM pre_ride_email_v3_queue WHERE queue_status IN ('queued','submit_dry_run_ready','live_submit_ready')"),
            'future_active' => $this->countSql("SELECT COUNT(*) FROM pre_ride_email_v3_queue WHERE queue_status IN ('queued','submit_dry_run_ready','live_submit_ready') AND pickup_datetime IS NOT NULL AND pickup_datetime > NOW()"),
            'submit_dry_run_ready' => $this->countSql("SELECT COUNT(*) FROM pre_ride_email_v3_queue WHERE queue_status = 'submit_dry_run_ready'"),
            'live_submit_ready' => $this->countSql("SELECT COUNT(*) FROM pre_ride_email_v3_queue WHERE queue_status = 'live_submit_ready'"),
            'submitted' => $this->countSql("SELECT COUNT(*) FROM pre_ride_email_v3_queue WHERE queue_status = 'submitted'"),
            'blocked' => $this->countSql("SELECT COUNT(*) FROM pre_ride_email_v3_queue WHERE queue_status = 'blocked'"),
            'status_counts' => $statusCounts,
            'recent_rows' => $recentRows,
        ];
    }

    /** @return array<string,mixed> */
    public function cronStatus(): array
    {
        $logsDir = rtrim($this->appRoot, '/') . '/logs';
        if (!is_dir($logsDir)) {
            $logsDir = rtrim($this->appRoot, '/') . '/storage/logs';
        }
        $now = time();
        $items = [];
        $requiredPresent = true;
        $criticalFresh = true;

        foreach ($this->cronLogs as $key => $file) {
            $path = $logsDir . '/' . $file;
            $exists = is_file($path);
            $readable = is_readable($path);
            $mtime = $exists ? (int)filemtime($path) : 0;
            $age = $mtime > 0 ? max(0, $now - $mtime) : null;
            $fresh = $exists && $readable && $age !== null && $age <= 180;
            $requiredPresent = $requiredPresent && $exists && $readable;
            $criticalFresh = $criticalFresh && $fresh;

            $items[$key] = [
                'file' => $path,
                'exists' => $exists,
                'readable' => $readable,
                'mtime' => $mtime > 0 ? date(DATE_ATOM, $mtime) : '',
                'age_seconds' => $age,
                'fresh' => $fresh,
                'latest_summary' => $exists && $readable ? $this->latestLogLine($path, 'SUMMARY') : '',
                'latest_finish' => $exists && $readable ? $this->latestLogLine($path, 'finish') : '',
            ];
        }

        return [
            'logs_dir' => $logsDir,
            'all_required_present' => $requiredPresent,
            'critical_fresh' => $criticalFresh,
            'fresh_threshold_seconds' => 180,
            'items' => $items,
        ];
    }

    /** @return array<string,mixed> */
    public function liveSubmitConfigStatus(): array
    {
        $configPath = dirname($this->appRoot) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
        $config = [];
        $loaded = false;
        $error = '';

        if (!is_file($configPath)) {
            $error = 'Config file not found.';
        } else {
            try {
                $data = require $configPath;
                if (is_array($data)) {
                    $config = $data;
                    $loaded = true;
                } else {
                    $error = 'Config file did not return an array.';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $enabled = (bool)($config['enabled'] ?? false);
        $mode = (string)($config['mode'] ?? 'disabled');
        $adapter = (string)($config['adapter'] ?? 'disabled');
        $hard = (bool)($config['hard_enable_live_submit'] ?? false);
        $ack = $this->acknowledgementPresent($config);
        $operatorApprovalRequired = (bool)($config['operator_approval_required'] ?? true);
        $allowedLessors = $config['allowed_lessors'] ?? [];
        if (!is_array($allowedLessors)) {
            $allowedLessors = [];
        }

        $blocks = [];
        if (!$loaded) {
            $blocks[] = 'server live-submit config is missing or invalid';
        }
        if (!$enabled) {
            $blocks[] = 'enabled is false';
        }
        if ($mode !== 'live') {
            $blocks[] = 'mode is not live';
        }
        if (!$ack) {
            $blocks[] = 'required acknowledgement phrase is not present';
        }
        if ($adapter === '' || $adapter === 'disabled') {
            $blocks[] = 'adapter is disabled';
        }
        if (!$hard) {
            $blocks[] = 'hard_enable_live_submit is false';
        }

        return [
            'config_path' => $configPath,
            'config_loaded' => $loaded,
            'config_error' => $error,
            'enabled' => $enabled,
            'mode' => $mode,
            'adapter' => $adapter,
            'hard_enable_live_submit' => $hard,
            'acknowledgement_present' => $ack,
            'operator_approval_required' => $operatorApprovalRequired,
            'allowed_lessors' => array_values(array_map('strval', $allowedLessors)),
            'ok_for_future_live_submit' => empty($blocks),
            'blocks' => $blocks,
        ];
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function safetyStatus(array $config): array
    {
        $hard = (bool)($config['hard_enable_live_submit'] ?? false);
        $enabled = (bool)($config['enabled'] ?? false);
        $mode = (string)($config['mode'] ?? 'disabled');
        $adapter = (string)($config['adapter'] ?? 'disabled');

        return [
            'edxeix_call_from_report' => false,
            'aade_call_from_report' => false,
            'db_writes_from_report' => false,
            'production_submission_jobs' => false,
            'production_submission_attempts' => false,
            'production_pre_ride_tool_change' => false,
            'live_submit_enabled_by_config' => $enabled && $mode === 'live' && $adapter !== 'disabled',
            'hard_live_enabled' => $hard,
            'safety_message' => $hard ? 'WARNING: hard live submit flag is true in config.' : 'Live submit remains hard-disabled.',
        ];
    }

    /** @param array<string,bool> $schema @param array<string,mixed> $cron @param array<string,mixed> $config @param array<string,mixed> $queue */
    private function nextAction(array $schema, array $cron, array $config, array $queue): string
    {
        foreach ($schema as $name => $ok) {
            if (!$ok) {
                return 'Install or repair V3 schema table: ' . $name;
            }
        }
        if (!$cron['all_required_present']) {
            return 'Check V3 cron log files and cron entries.';
        }
        if (!$cron['critical_fresh']) {
            return 'Check V3 cron freshness; one or more logs are stale.';
        }
        if ((int)($queue['live_submit_ready'] ?? 0) > 0) {
            return 'Review live_submit_ready rows in V3 rehearsal/payload audit before any live approval.';
        }
        if ((int)($queue['submit_dry_run_ready'] ?? 0) > 0) {
            return 'Let/readiness cron promote safe rows to live_submit_ready, then review payload audit.';
        }
        if ((int)($queue['active'] ?? 0) > 0) {
            return 'Review active V3 queue rows and dry-run readiness results.';
        }
        if (!$config['config_loaded']) {
            return 'Install disabled V3 live-submit config so the master gate can report cleanly.';
        }
        return 'Waiting for the next future-safe Bolt pre-ride email.';
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $table);
        if (!$stmt->execute()) {
            return false;
        }
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['c'] ?? 0) > 0;
    }

    private function countSql(string $sql): int
    {
        $res = $this->db->query($sql);
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_row();
        return (int)($row[0] ?? 0);
    }

    private function databaseName(): string
    {
        $res = $this->db->query('SELECT DATABASE()');
        if (!$res) {
            return '';
        }
        $row = $res->fetch_row();
        return (string)($row[0] ?? '');
    }

    private function latestLogLine(string $path, string $needle): string
    {
        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return '';
        }
        $fh = fopen($path, 'rb');
        if (!$fh) {
            return '';
        }
        $read = min(120000, $size);
        if ($size > $read) {
            fseek($fh, -$read, SEEK_END);
        }
        $chunk = (string)fread($fh, $read);
        fclose($fh);
        $lines = preg_split('/\r\n|\r|\n/', $chunk) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim((string)$lines[$i]);
            if ($line !== '' && stripos($line, $needle) !== false) {
                return $line;
            }
        }
        return '';
    }

    /** @param array<string,mixed> $config */
    private function acknowledgementPresent(array $config): bool
    {
        foreach (['acknowledgement', 'acknowledgement_phrase', 'required_acknowledgement', 'required_acknowledgement_phrase'] as $key) {
            $value = trim((string)($config[$key] ?? ''));
            if ($value !== '') {
                return true;
            }
        }
        return false;
    }
}
