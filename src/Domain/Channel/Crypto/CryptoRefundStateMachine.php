<?php

namespace Payroad\Domain\Channel\Crypto;

use Payroad\Domain\Refund\RefundStateMachineInterface;
use Payroad\Domain\Refund\RefundStatus;

/**
 * Refund state machine for crypto payments.
 *
 * PENDING ──► PROCESSING ──► SUCCEEDED
 *                           └──► FAILED
 *
 * Crypto refunds are on-chain transactions and are always asynchronous:
 * the refund must pass through PROCESSING before it can succeed or fail.
 * Direct PENDING → SUCCEEDED is not permitted.
 */
final class CryptoRefundStateMachine implements RefundStateMachineInterface
{
    public function canTransition(RefundStatus $from, RefundStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($from) {
            RefundStatus::PENDING    => in_array($to, [
                RefundStatus::PROCESSING,
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
