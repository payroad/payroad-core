<?php

namespace Payroad\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Exception\InvalidTransitionException;

/**
 * State machine for the generic card payment flow.
 * Provider-specific sub-states (e.g. Stripe's requires_capture) are handled
 * inside the integration package before mapping to these universal statuses.
 *
 * PENDING ──► AWAITING_CONFIRMATION ──► PROCESSING ──► SUCCEEDED
 *         └──► PROCESSING            └──► FAILED
 *         └──► FAILED                └──► CANCELED
 */
final class CardStateMachine implements AttemptStateMachineInterface
{
    public function canTransition(PaymentAttempt $attempt, AttemptStatus $to): bool
    {
        if ($attempt->getStatus()->isTerminal()) {
            return false;
        }

        return match ($attempt->getStatus()) {
            AttemptStatus::PENDING => in_array($to, [
                AttemptStatus::AWAITING_CONFIRMATION,
                AttemptStatus::PROCESSING,
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
            throw new InvalidTransitionException($attempt->getStatus(), $to, 'card');
        }

        $attempt->transitionTo($to, $providerStatus, $reason);
    }
}
