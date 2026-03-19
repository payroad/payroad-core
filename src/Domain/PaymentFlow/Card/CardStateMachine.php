<?php

namespace Payroad\Domain\PaymentFlow\Card;

use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptStatus;

/**
 * State machine for the generic card payment flow.
 *
 * Standard charge:
 *   PENDING ──► PROCESSING ──► SUCCEEDED
 *           └──► FAILED       └──► FAILED
 *
 * Authorize + capture:
 *   PENDING ──► AUTHORIZED ──► PROCESSING ──► SUCCEEDED
 *                          └──► SUCCEEDED    └──► FAILED
 *                          └──► CANCELED
 *                          └──► FAILED
 *
 * 3DS / redirect:
 *   PENDING ──► AWAITING_CONFIRMATION ──► PROCESSING ──► SUCCEEDED
 *                                     └──► FAILED       └──► FAILED
 *                                     └──► CANCELED
 */
final class CardStateMachine implements AttemptStateMachineInterface
{
    public function canTransition(AttemptStatus $from, AttemptStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($from) {
            AttemptStatus::PENDING => in_array($to, [
                AttemptStatus::AUTHORIZED,
                AttemptStatus::AWAITING_CONFIRMATION,
                AttemptStatus::PROCESSING,
                AttemptStatus::FAILED,
            ], true),

            AttemptStatus::AUTHORIZED => in_array($to, [
                AttemptStatus::PROCESSING,
                AttemptStatus::SUCCEEDED,
                AttemptStatus::CANCELED,
                AttemptStatus::FAILED,
            ], true),

            AttemptStatus::AWAITING_CONFIRMATION => in_array($to, [
                AttemptStatus::PROCESSING,
                AttemptStatus::FAILED,
                AttemptStatus::CANCELED,
            ], true),

            AttemptStatus::PROCESSING => in_array($to, [
                AttemptStatus::SUCCEEDED,
                AttemptStatus::FAILED,
                AttemptStatus::EXPIRED,
            ], true),

            default => false,
        };
    }
}
