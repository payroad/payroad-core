<?php

namespace Payroad\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Exception\InvalidTransitionException;

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
    public function canTransition(PaymentAttempt $attempt, AttemptStatus $to): bool
    {
        if ($attempt->getStatus()->isTerminal()) {
            return false;
        }

        return match ($attempt->getStatus()) {
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

    public function applyTransition(
        PaymentAttempt $attempt,
        AttemptStatus  $to,
        string         $providerStatus,
        string         $reason  = '',
        array          $context = []
    ): void {
        if (!$this->canTransition($attempt, $to)) {
            throw new InvalidTransitionException($attempt->getStatus(), $to, 'crypto');
        }

        $attempt->transitionTo($to, $providerStatus, $reason);
    }
}
