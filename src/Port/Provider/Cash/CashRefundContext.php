<?php

namespace Payroad\Port\Provider\Cash;

/**
 * Caller-supplied context for initiating a cash refund.
 */
final readonly class CashRefundContext
{
    public function __construct(
        /** Preferred cash pickup network: 'oxxo', 'boleto', 'konbini', etc. */
        public ?string $preferredNetwork = null,
    ) {}
}
