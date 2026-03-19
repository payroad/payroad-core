<?php

namespace Payroad\Port\Provider\P2P;

/**
 * Flow-specific context for P2P (bank transfer) payment initiation.
 * Provides customer identity details required by bank transfer providers.
 */
final readonly class P2PAttemptContext
{
    public function __construct(
        public string  $customerName,
        /** BIC / routing code of the customer's bank. Optional; used for pre-validation. */
        public ?string $customerBankCode = null,
    ) {}
}
