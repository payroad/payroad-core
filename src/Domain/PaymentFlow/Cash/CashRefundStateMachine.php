<?php

namespace Payroad\Domain\PaymentFlow\Cash;

use Payroad\Domain\Refund\RefundStateMachineInterface;
use Payroad\Domain\Refund\RefundStatus;

/**
 * Refund state machine for cash payments.
 *
 * PENDING ──► SUCCEEDED
 *         └──► FAILED
 *
 * Cash refunds are direct and synchronous: there is no intermediate PROCESSING state
 * because the provider either confirms the cash-back immediately or reports a failure.
 */
final class CashRefundStateMachine implements RefundStateMachineInterface
{
    public function canTransition(RefundStatus $from, RefundStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($from) {
            RefundStatus::PENDING => in_array($to, [
                RefundStatus::SUCCEEDED,
                RefundStatus::FAILED,
            ], true),

            default => false,
        };
    }
}
