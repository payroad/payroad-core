<?php

namespace Payroad\Domain\Refund;

interface RefundStateMachineInterface
{
    /**
     * Returns true if a transition from $from to $to is allowed.
     * Pure predicate — no side effects, no access to the aggregate.
     */
    public function canTransition(RefundStatus $from, RefundStatus $to): bool;
}
