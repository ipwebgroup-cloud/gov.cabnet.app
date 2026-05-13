<?php

declare(strict_types=1);

namespace Bridge\BoltMailV3;

/**
 * Contract for future V3 live-submit adapters.
 *
 * Implementations MUST NOT submit unless the caller has already passed:
 * - V3 master gate
 * - V3 per-row operator approval
 * - verified starting-point guard
 * - future/time/price/payload checks
 */
interface LiveSubmitAdapterV3
{
    public function name(): string;

    /**
     * Returns true only for an adapter that is capable of making a real EDXEIX call.
     * Disabled and dry-run adapters must return false.
     */
    public function isLiveCapable(): bool;

    /**
     * @param array<string,mixed> $edxeixPayload Final EDXEIX field package.
     * @param array<string,mixed> $context Non-secret row/gate context.
     * @return array<string,mixed> Result envelope. Must include submitted=true only after a real confirmed submit.
     */
    public function submit(array $edxeixPayload, array $context = []): array;
}
