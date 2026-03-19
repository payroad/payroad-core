<?php

namespace Payroad\Port\Provider\Cash;

/**
 * Flow-specific context for cash payment initiation.
 * Used to deliver the deposit code to the customer.
 */
final readonly class CashAttemptContext
{
    public function __construct(
        public string  $customerPhone,
        public ?string $customerEmail    = null,
        /** Cash network identifier: 'oxxo', 'boleto', 'konbini', etc. */
        public ?string $preferredNetwork = null,
    ) {}
}
