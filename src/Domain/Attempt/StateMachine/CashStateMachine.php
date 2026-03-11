<?php

namespace Payroad\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Exception\InvalidTransitionException;

/**
 * State machine for the generic cash payment flow.
 *
 * PENDING ──► AWAITING_CONFIRMATION ──► SUCCEEDED
 *         └──► FAILED               └──► EXPIRED
 */
final class CashStateMachine implements AttemptStateMachineInterface
{
    public function canTransition(PaymentAttempt $attempt, AttemptStatus $to): bool
    {
        if ($attempt->getStatus()->isTerminal()) {
            return false;
        }

        return match ($attempt->getStatus()) {
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

    public function applyTransition(
        PaymentAttempt $attempt,
        AttemptStatus  $to,
        string         $providerStatus,
        string         $reason  = '',
        array          $context = []
    ): void {
        if (!$this->canTransition($attempt, $to)) {
            throw new InvalidTransitionException($attempt->getStatus(), $to, 'cash');
        }

        $attempt->transitionTo($to, $providerStatus, $reason);
    }
}
