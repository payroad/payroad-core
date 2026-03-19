<?php

namespace Payroad\Port\Provider\Crypto;

use Payroad\Port\Provider\RefundData;

/**
 * Data interface for crypto-based refunds.
 * Implementations live in provider packages (e.g. payroad/binance-crypto-provider).
 */
interface CryptoRefundData extends RefundData
{
    /** Transaction hash of the return transfer on the blockchain. */
    public function getReturnTxHash(): ?string;

    /** Wallet address the refund was sent to. */
    public function getReturnAddress(): ?string;
}
