<?php

namespace Payroad\Domain\Channel\Card;

use Payroad\Domain\Refund\RefundStateMachineInterface;
use Payroad\Domain\Refund\RefundStatus;

/**
 * Refund state machine for card payments.
 *
 * PENDING ──► PROCESSING ──► SUCCEEDED
 *         └──► SUCCEEDED    └──► FAILED
 *         └──► FAILED
 *
 * Card refunds may process asynchronously via the card network (PENDING → PROCESSING → SUCCEEDED),
 * or synchronously for some providers (PENDING → SUCCEEDED).
 */
final class CardRefundStateMachine implements RefundStateMachineInterface
{
    public function canTransition(RefundStatus $from, RefundStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($from) {
            RefundStatus::PENDING    => in_array($to, [
                RefundStatus::PROCESSING,
                RefundStatus::SUCCEEDED,
                RefundStatus::FAILED,
            ], true),

            RefundStatus::PROCESSING => in_array($to, [
                RefundStatus::SUCCEEDED,
                RefundStatus::FAILED,
            ], true),

            default => false,
        };
    }
}
