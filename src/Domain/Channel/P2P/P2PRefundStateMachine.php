<?php

namespace Payroad\Domain\Channel\P2P;

use Payroad\Domain\Refund\RefundStateMachineInterface;
use Payroad\Domain\Refund\RefundStatus;

/**
 * Refund state machine for P2P payments.
 *
 * PENDING ──► PROCESSING ──► SUCCEEDED
 *         └──► SUCCEEDED    └──► FAILED
 *         └──► FAILED
 *
 * P2P refunds follow the same async flow as cards: the provider may
 * confirm synchronously (PENDING → SUCCEEDED) or schedule a bank transfer
 * that moves through PROCESSING first.
 */
final class P2PRefundStateMachine implements RefundStateMachineInterface
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
