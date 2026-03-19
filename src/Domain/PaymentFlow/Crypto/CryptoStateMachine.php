<?php

namespace Payroad\Domain\PaymentFlow\Crypto;

use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptStatus;

/**
 * State machine for the generic crypto payment flow.
 * Intermediate confirmation updates (confirmations: 3/6) do not change
 * AttemptStatus — they only update SpecificData via the provider.
 *
 * PENDING ──► PROCESSING ──► SUCCEEDED
 *                        └──► FAILED
 *                        └──► EXPIRED
 */
final class CryptoStateMachine implements AttemptStateMachineInterface
{
    public function canTransition(AttemptStatus $from, AttemptStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($from) {
            AttemptStatus::PENDING => in_array($to, [
                AttemptStatus::PROCESSING,
                AttemptStatus::FAILED,
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
