<?php

namespace Payroad\Domain\PaymentFlow\Crypto;

use Payroad\Domain\Attempt\AttemptData;

/**
 * Data interface for cryptocurrency payment attempts.
 * Implementations live in provider packages (e.g. payroad/binance-crypto-provider).
 */
interface CryptoAttemptData extends AttemptData
{
    /** Wallet address to which the customer should send funds. */
    public function getWalletAddress(): string;

    /** Crypto currency code the customer must send (e.g. "btc", "USDT", "usdttrc20"). */
    public function getPayCurrency(): string;

    /** Amount in crypto the customer must send. */
    public function getPayAmount(): string;

    /** Number of blockchain confirmations received so far. */
    public function getConfirmationCount(): int;

    /** Minimum confirmations required to consider the transaction final. */
    public function getRequiredConfirmations(): int;
}
