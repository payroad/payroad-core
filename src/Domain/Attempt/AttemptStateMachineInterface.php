<?php

namespace Payroad\Domain\Attempt;

interface AttemptStateMachineInterface
{
    /**
     * Returns true if a transition from $from to $to is allowed.
     * Pure predicate — no side effects, no access to the aggregate.
     */
    public function canTransition(AttemptStatus $from, AttemptStatus $to): bool;
}
