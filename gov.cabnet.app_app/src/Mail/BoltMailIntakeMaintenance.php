<?php

namespace Bridge\Mail;

use Bridge\Database;
use DateTimeImmutable;
use DateTimeZone;

final class BoltMailIntakeMaintenance
{
    public function __construct(
        private readonly Database $db,
        private readonly DateTimeZone $timezone
    ) {
    }

    /**
     * Close stale open intake rows whose pickup time is now in the past.
     *
     * This is intentionally narrow:
     * - only parsed rows
     * - only rows not linked to a normalized booking
     * - only open/actionable statuses that should not remain actionable after pickup time
     * - no EDXEIX jobs are created and no live submit is performed
     */
    public function expirePastOpenRows(): int
    {
        $now = (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s');

        return $this->db->execute(
            "UPDATE bolt_mail_intake
             SET safety_status = 'blocked_past',
                 rejection_reason = CASE
                    WHEN rejection_reason IS NULL OR rejection_reason = ''
                        THEN CONCAT('Expired by mail intake maintenance because pickup time is now in the past. Checked at ', ?)
                    ELSE CONCAT(rejection_reason, '\nExpired by mail intake maintenance because pickup time is now in the past. Checked at ', ?)
                 END,
                 updated_at = NOW()
             WHERE parse_status = 'parsed'
               AND safety_status IN ('future_candidate', 'blocked_too_soon', 'needs_review')
               AND linked_booking_id IS NULL
               AND parsed_pickup_at IS NOT NULL
               AND parsed_pickup_at <= ?",
            [$now, $now, $now],
            'sss'
        );
    }
}
