<?php

namespace Payroad\Domain\Channel\Crypto;

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

    /**
     * Actual crypto amount received from the customer so far.
     * Null until the first payment transaction is detected.
     * Used to track underpayments (PARTIALLY_PAID state).
     */
    public function getActualPaidAmount(): ?string;

    /**
     * Hosted payment page URL for providers that use a redirect flow
     * instead of exposing a raw wallet address (e.g. CoinGate).
     * Null for providers that give a wallet address directly.
     */
    public function getPaymentUrl(): ?string;

    /**
     * Optional memo / destination tag required by some networks (e.g. XRP, XLM, EOS).
     * Null when not applicable.
     */
    public function getMemo(): ?string;
}
