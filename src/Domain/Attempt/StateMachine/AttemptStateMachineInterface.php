<?php

namespace Payroad\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;

interface AttemptStateMachineInterface
{
    public function canTransition(PaymentAttempt $attempt, AttemptStatus $to): bool;

    /**
     * Validates and applies the transition.
     * Throws InvalidTransitionException when the transition is not allowed.
     *
     * @param string $providerStatus Raw status string from the provider (e.g. "requires_capture").
     * @param string $reason         Human-readable reason for failures.
     * @param array  $context        Provider-specific context for updating SpecificData.
     */
    public function applyTransition(
        PaymentAttempt $attempt,
        AttemptStatus  $to,
        string         $providerStatus,
        string         $reason  = '',
        array          $context = []
    ): void;
}
