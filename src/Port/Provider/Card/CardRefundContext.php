<?php

namespace Payroad\Port\Provider\Card;

/**
 * Caller-supplied context for initiating a card refund.
 */
final readonly class CardRefundContext
{
    public function __construct(
        /** Refund reason: 'duplicate', 'fraudulent', 'requested_by_customer', etc. */
        public ?string $reason = null,
    ) {}
}
