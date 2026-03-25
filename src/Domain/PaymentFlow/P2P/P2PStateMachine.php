<?php

namespace Payroad\Domain\PaymentFlow\P2P;

use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptStatus;

/**
 * State machine for the generic P2P (bank transfer) payment flow.
 *
 * PENDING ──► AWAITING_CONFIRMATION ──► PROCESSING ──► SUCCEEDED
 *         └──► FAILED               └──► FAILED       └──► FAILED
 *                                   └──► EXPIRED
 *                                   └──► CANCELED
 */
final class P2PStateMachine implements AttemptStateMachineInterface
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
                AttemptStatus::PROCESSING,
                AttemptStatus::FAILED,
                AttemptStatus::EXPIRED,
                AttemptStatus::CANCELED,
            ], true),

            AttemptStatus::PROCESSING => in_array($to, [
                AttemptStatus::SUCCEEDED,
                AttemptStatus::FAILED,
            ], true),

            default => false,
        };
    }
}
