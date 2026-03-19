<?php

namespace Payroad\Port\Provider\Crypto;

/**
 * Caller-supplied context for initiating a crypto refund.
 */
final readonly class CryptoRefundContext
{
    public function __construct(
        /** Wallet address to send the refund to. Required for on-chain returns. */
        public string  $returnAddress,
        /** Network override: 'erc20', 'trc20', etc. Defaults to original deposit network. */
        public ?string $network = null,
    ) {}
}
