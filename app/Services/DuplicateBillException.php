<?php

namespace App\Services;

use App\Models\UtilityBillingProof;
use RuntimeException;

/**
 * Thrown when a bill would duplicate one that already exists for the same
 * tenant, room, type and month. Carries the existing bill so the screen can
 * link straight to it — usually to set the final amount on an estimate.
 */
class DuplicateBillException extends RuntimeException
{
    public function __construct(public readonly UtilityBillingProof $existing)
    {
        $room = optional($existing->lease)->room_no;

        parent::__construct(sprintf(
            '%s already has a %s bill for %s%s (bill #%d%s). %s',
            optional($existing->tenant)->name ?? 'This tenant',
            strtolower((string) $existing->billing_type),
            $existing->billing_month,
            $room ? ' in room ' . $room : '',
            $existing->id,
            $existing->isUnreconciledEstimate() ? ', estimated' : '',
            $existing->isUnreconciledEstimate()
                ? 'Open that bill and set its final amount instead of issuing a new one.'
                : 'Void it first if it was wrong, or post an adjustment on it.'
        ));
    }
}
