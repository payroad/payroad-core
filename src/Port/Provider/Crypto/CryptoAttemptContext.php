<?php

namespace Payroad\Port\Provider\Crypto;

/**
 * Flow-specific context for crypto payment initiation.
 * Specifies network preference and optional memo for on-chain deposits.
 */
final readonly class CryptoAttemptContext
{
    public function __construct(
        /** Network identifier: 'erc20', 'trc20', 'bep20', 'bitcoin', 'solana', etc. */
        public string  $network,
        /** Required by some chains (e.g. XRP, EOS, Stellar) to identify the recipient account. */
        public ?string $memo = null,
    ) {}
}
