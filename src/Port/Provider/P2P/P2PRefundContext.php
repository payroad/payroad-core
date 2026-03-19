<?php

namespace Payroad\Port\Provider\P2P;

/**
 * Caller-supplied context for initiating a P2P (bank transfer) refund.
 */
final readonly class P2PRefundContext
{
    public function __construct(
        /** Optional reason for the refund as reported by the merchant. */
        public ?string $reason = null,
    ) {}
}
