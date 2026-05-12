<?php
/**
 * gov.cabnet.app — EDXEIX submit preflight gate.
 *
 * Shared safety evaluator for the future mobile/server-side EDXEIX submit workflow.
 * This class performs checks only. It does not call EDXEIX and does not write data.
 */

declare(strict_types=1);

namespace Bridge\Edxeix;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class EdxeixSubmitPreflightGate
{
    public const VERSION = 'v0.1.0-preflight-gate-no-live-submit';

    /**
     * @param array<string,mixed> $fields Parsed Bolt pre-ride fields.
     * @param array<string,mixed> $mapping Read-only EDXEIX mapping lookup result.
     * @param array<string,mixed>|null $capture Latest sanitized EDXEIX submit capture metadata.
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function evaluate(array $fields, array $mapping, ?array $capture = null, array $options = []): array
    {
        $guardMinutes = max(1, (int)($options['future_guard_minutes'] ?? 30));
        $timezone = (string)($options['timezone'] ?? 'Europe/Athens');
        $liveConnectorEnabled = !empty($options['live_connector_enabled']);
        $operatorFinalConfirmed = !empty($options['operator_final_confirmed']);
        $mapPointConfirmed = !empty($options['map_point_confirmed']);

        $technicalBlockers = [];
        $liveBlockers = [];
        $warnings = [];
        $facts = [];

        foreach ($this->requiredFields() as $key => $label) {
            if ($this->value($fields, $key) === '') {
                $technicalBlockers[] = 'missing_' . $key;
                $warnings[] = 'Missing parsed field: ' . $label;
            }
        }

        $future = $this->futureStatus($this->value($fields, 'pickup_datetime_local'), $guardMinutes, $timezone);
        $facts['future_status'] = $future;
        if (!$future['ok']) {
            $technicalBlockers[] = (string)$future['blocker'];
        }

        $lessorId = $this->value($mapping, 'lessor_id');
        $driverId = $this->value($mapping, 'driver_id');
        $vehicleId = $this->value($mapping, 'vehicle_id');
        $startingPointId = $this->value($mapping, 'starting_point_id');

        if (empty($mapping['ok'])) {
            $technicalBlockers[] = 'edxeix_mapping_not_ready';
        }
        if ($lessorId === '') {
            $technicalBlockers[] = 'missing_edxeix_lessor_id';
        }
        if ($driverId === '') {
            $technicalBlockers[] = 'missing_edxeix_driver_id';
        }
        if ($vehicleId === '') {
            $technicalBlockers[] = 'missing_edxeix_vehicle_id';
        }
        if ($startingPointId === '') {
            $warnings[] = 'Starting point ID is empty; browser/live EDXEIX selection may be required.';
        }

        $captureStatus = $this->captureStatus($capture);
        $facts['capture_status'] = $captureStatus;
        if (!$captureStatus['ok']) {
            $technicalBlockers[] = (string)$captureStatus['blocker'];
        }

        foreach ((array)($mapping['warnings'] ?? []) as $warning) {
            $warning = trim((string)$warning);
            if ($warning !== '') {
                $warnings[] = 'Mapping warning: ' . $warning;
            }
        }

        if (!$mapPointConfirmed) {
            $liveBlockers[] = 'pickup_map_point_not_confirmed';
        }
        if (!$operatorFinalConfirmed) {
            $liveBlockers[] = 'operator_final_confirmation_missing';
        }
        if (!$liveConnectorEnabled) {
            $liveBlockers[] = 'server_side_edxeix_connector_disabled';
        }

        $technicalBlockers = array_values(array_unique(array_filter($technicalBlockers)));
        $liveBlockers = array_values(array_unique(array_filter(array_merge($technicalBlockers, $liveBlockers))));
        $warnings = array_values(array_unique(array_filter($warnings)));

        $technicalReady = count($technicalBlockers) === 0;
        $liveReady = count($liveBlockers) === 0;

        return [
            'ok' => true,
            'version' => self::VERSION,
            'technical_ready' => $technicalReady,
            'dry_run_payload_allowed' => $technicalReady,
            'live_submit_allowed' => $liveReady,
            'live_submit_enabled' => $liveConnectorEnabled,
            'technical_blockers' => $technicalBlockers,
            'live_blockers' => $liveBlockers,
            'warnings' => $warnings,
            'facts' => $facts,
            'required_fields' => $this->requiredFields(),
            'edxeix_ids' => [
                'lessor_id' => $lessorId,
                'driver_id' => $driverId,
                'vehicle_id' => $vehicleId,
                'starting_point_id' => $startingPointId,
            ],
            'safety_contract' => [
                'calls_edxeix' => false,
                'calls_bolt' => false,
                'calls_aade' => false,
                'writes_database' => false,
                'live_submit_default' => false,
            ],
        ];
    }

    /** @return array<string,string> */
    private function requiredFields(): array
    {
        return [
            'customer_name' => 'Passenger name',
            'customer_phone' => 'Passenger mobile',
            'driver_name' => 'Driver name',
            'vehicle_plate' => 'Vehicle plate',
            'pickup_address' => 'Pickup address',
            'dropoff_address' => 'Drop-off address',
            'pickup_datetime_local' => 'Pickup datetime',
            'end_datetime_local' => 'Estimated end datetime',
            'estimated_price_amount' => 'Price amount',
        ];
    }

    /** @param array<string,mixed> $source */
    private function value(array $source, string $key): string
    {
        return trim((string)($source[$key] ?? ''));
    }

    /** @return array<string,mixed> */
    private function futureStatus(string $raw, int $guardMinutes, string $timezone): array
    {
        if ($raw === '') {
            return [
                'ok' => false,
                'label' => 'Missing pickup datetime',
                'blocker' => 'missing_pickup_datetime',
                'minutes_until_pickup' => null,
            ];
        }

        try {
            $tz = new DateTimeZone($timezone !== '' ? $timezone : 'Europe/Athens');
            $pickup = new DateTimeImmutable($raw, $tz);
            $now = new DateTimeImmutable('now', $tz);
            $seconds = $pickup->getTimestamp() - $now->getTimestamp();
            $minutes = (int)floor($seconds / 60);

            if ($seconds <= 0) {
                return [
                    'ok' => false,
                    'label' => 'Past or already due',
                    'blocker' => 'pickup_not_future',
                    'minutes_until_pickup' => $minutes,
                ];
            }

            if ($seconds < ($guardMinutes * 60)) {
                return [
                    'ok' => false,
                    'label' => 'Future but inside guard window',
                    'blocker' => 'pickup_inside_future_guard',
                    'minutes_until_pickup' => $minutes,
                ];
            }

            return [
                'ok' => true,
                'label' => 'Future guard passed',
                'blocker' => '',
                'minutes_until_pickup' => $minutes,
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'label' => 'Invalid pickup datetime',
                'blocker' => 'invalid_pickup_datetime',
                'minutes_until_pickup' => null,
            ];
        }
    }

    /**
     * @param array<string,mixed>|null $capture
     * @return array<string,mixed>
     */
    private function captureStatus(?array $capture): array
    {
        if (!$capture) {
            return [
                'ok' => false,
                'label' => 'No sanitized EDXEIX submit capture available',
                'blocker' => 'missing_sanitized_submit_capture',
                'capture_id' => '',
            ];
        }

        $method = strtoupper(trim((string)($capture['form_method'] ?? '')));
        $actionHost = trim((string)($capture['action_host'] ?? ''));
        $actionPath = trim((string)($capture['action_path'] ?? ''));
        $requiredFields = trim((string)($capture['required_field_names'] ?? ''));

        $missing = [];
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $missing[] = 'valid_form_method';
        }
        if ($actionHost === '') {
            $missing[] = 'action_host';
        }
        if ($actionPath === '') {
            $missing[] = 'action_path';
        }
        if ($requiredFields === '') {
            $missing[] = 'required_field_names';
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'label' => 'Sanitized submit capture is incomplete: ' . implode(', ', $missing),
                'blocker' => 'incomplete_sanitized_submit_capture',
                'capture_id' => (string)($capture['id'] ?? ''),
                'missing' => $missing,
            ];
        }

        return [
            'ok' => true,
            'label' => 'Sanitized submit capture available',
            'blocker' => '',
            'capture_id' => (string)($capture['id'] ?? ''),
            'method' => $method,
            'action_host' => $actionHost,
            'action_path' => $actionPath,
        ];
    }
}
