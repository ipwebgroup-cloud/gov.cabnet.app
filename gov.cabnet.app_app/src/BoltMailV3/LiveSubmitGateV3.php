<?php
/**
 * gov.cabnet.app — V3 live-submit master gate.
 *
 * Purpose:
 * - Central read-only gate for any future V3 live EDXEIX submit worker.
 * - Default posture is hard-disabled when config is missing, incomplete, or disabled.
 * - Accepts both canonical and legacy config key aliases so disabled config files can be loaded cleanly.
 *
 * Safety:
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No database writes.
 * - No production submission table access.
 */

declare(strict_types=1);

namespace Bridge\BoltMailV3;

final class LiveSubmitGateV3
{
    public const VERSION = 'v3.0.31-live-submit-gate-config-hygiene';
    public const CONFIG_BASENAME = 'pre_ride_email_v3_live_submit.php';
    public const REQUIRED_ACK = 'I EXPLICITLY APPROVE V3 LIVE EDXEIX SUBMIT';

    /** @return array<string,mixed> */
    public static function evaluate(?array $config = null): array
    {
        $loaded = false;
        $configPath = '';
        $loadError = '';

        if ($config === null) {
            $loadedConfig = self::loadConfig($loadError, $configPath);
            $loadError = trim((string)$loadError);
            if (is_array($loadedConfig)) {
                $config = $loadedConfig;
                $loaded = true;
                $loadError = '';
            } else {
                $config = [];
            }
        } else {
            $loaded = true;
            $loadError = '';
        }

        $enabled = self::boolVal($config['enabled'] ?? false);
        $mode = strtolower(trim((string)($config['mode'] ?? 'disabled')));

        $ack = trim((string)self::configValue($config, ['acknowledgement', 'acknowledgement_phrase'], ''));
        $requiredAck = trim((string)self::configValue($config, ['required_acknowledgement', 'required_acknowledgement_phrase'], self::REQUIRED_ACK));
        if ($requiredAck === '') {
            $requiredAck = self::REQUIRED_ACK;
        }

        $requiredStatus = trim((string)($config['required_queue_status'] ?? 'live_submit_ready'));
        if ($requiredStatus === '') {
            $requiredStatus = 'live_submit_ready';
        }

        $minFuture = max(0, min(240, (int)($config['min_future_minutes'] ?? 1)));
        $adapter = trim((string)($config['adapter'] ?? 'disabled'));
        $operatorApprovalRequired = self::boolVal($config['operator_approval_required'] ?? true);
        $hardEnableLiveSubmit = self::boolVal($config['hard_enable_live_submit'] ?? false);

        $allowedLessorsRaw = $config['allowed_lessors'] ?? [];
        $allowedLessors = [];
        if (is_array($allowedLessorsRaw)) {
            foreach ($allowedLessorsRaw as $lessor) {
                $lessor = trim((string)$lessor);
                if ($lessor !== '') {
                    $allowedLessors[] = $lessor;
                }
            }
            $allowedLessors = array_values(array_unique($allowedLessors));
        }

        $blocks = [];
        $warnings = [];

        if (!$loaded) {
            $blocks[] = 'Server live-submit config is missing; gate is hard-disabled by default.';
        }
        if ($loadError !== '') {
            $blocks[] = 'Config load error: ' . $loadError;
        }
        if (!$enabled) {
            $blocks[] = 'enabled is false.';
        }
        if ($mode !== 'live') {
            $blocks[] = 'mode is not live.';
        }
        if ($ack !== $requiredAck) {
            $blocks[] = 'required acknowledgement phrase is not present.';
        }
        if ($adapter === '' || strtolower($adapter) === 'disabled') {
            $blocks[] = 'adapter is disabled.';
        }
        if (!$hardEnableLiveSubmit) {
            $blocks[] = 'hard_enable_live_submit is false.';
        }
        if ($operatorApprovalRequired) {
            $warnings[] = 'operator_approval_required is true; future worker must still require explicit per-row/operator approval.';
        }

        return [
            'ok_for_future_live_submit' => count($blocks) === 0,
            'version' => self::VERSION,
            'config_loaded' => $loaded,
            'config_path' => $configPath,
            'config_error' => $loadError,
            'enabled' => $enabled,
            'mode' => $mode,
            'adapter' => $adapter,
            'required_queue_status' => $requiredStatus,
            'min_future_minutes' => $minFuture,
            'allowed_lessors' => $allowedLessors,
            'operator_approval_required' => $operatorApprovalRequired,
            'hard_enable_live_submit' => $hardEnableLiveSubmit,
            'required_acknowledgement_present' => $ack === $requiredAck,
            'blocks' => $blocks,
            'warnings' => $warnings,
            'safety' => [
                'edxeix_call' => false,
                'aade_call' => false,
                'db_writes' => false,
                'production_submission_jobs' => false,
                'production_submission_attempts' => false,
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public static function loadConfig(?string &$error = null, ?string &$path = null): ?array
    {
        $error = '';
        $path = '';
        foreach (self::configCandidates() as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            $path = $candidate;
            try {
                $config = require $candidate;
                if (!is_array($config)) {
                    $error = 'Config file did not return an array.';
                    return null;
                }
                return $config;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                return null;
            }
        }
        $error = 'Config file not found.';
        return null;
    }

    /** @return array<int,string> */
    public static function configCandidates(): array
    {
        $home = getenv('HOME') ?: '/home/cabnet';
        return [
            '/home/cabnet/gov.cabnet.app_config/' . self::CONFIG_BASENAME,
            rtrim($home, '/') . '/gov.cabnet.app_config/' . self::CONFIG_BASENAME,
            dirname(__DIR__, 4) . '/gov.cabnet.app_config/' . self::CONFIG_BASENAME,
        ];
    }

    /** @param array<string,mixed> $config @param array<int,string> $keys */
    private static function configValue(array $config, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config)) {
                return $config[$key];
            }
        }
        return $default;
    }

    private static function boolVal(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        $text = strtolower(trim((string)$value));
        return in_array($text, ['1', 'true', 'yes', 'on'], true);
    }
}
