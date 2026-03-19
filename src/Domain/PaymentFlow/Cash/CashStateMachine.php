<?php

namespace Payroad\Domain\PaymentFlow\Cash;

use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptStatus;

/**
 * State machine for the generic cash payment flow.
 *
 * PENDING ──► AWAITING_CONFIRMATION ──► SUCCEEDED
 *         └──► FAILED               └──► EXPIRED
 */
final class CashStateMachine implements AttemptStateMachineInterface
{
    public function canTransition(AttemptStatus $from, AttemptStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($from) {
            AttemptStatus::PENDING => in_array($to, [
                AttemptStatus::AWAITING_CONFIRMATION,
                AttemptStatus::FAILED,
            ], true),

            AttemptStatus::AWAITING_CONFIRMATION => in_array($to, [
                AttemptStatus::SUCCEEDED,
                AttemptStatus::EXPIRED,
            ], true),

            default => false,
        };
    }
}
