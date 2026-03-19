<?php

namespace Payroad\Domain\Money;

/**
 * Represents a currency with its decimal precision (number of minor units per major unit).
 *
 * This value object is intentionally unaware of any currency registry or standard.
 * Precision must be provided explicitly by the caller — resolved from KnownCurrencies
 * in the infrastructure layer before entering the domain.
 *
 * Examples:
 *   new Currency('USD',  2)   // 1 USD = 100 cents
 *   new Currency('JPY',  0)   // 1 JPY = 1 yen (no subunits)
 *   new Currency('KWD',  3)   // 1 KWD = 1000 fils
 *   new Currency('BTC',  8)   // 1 BTC = 100_000_000 satoshis
 *   new Currency('ETH',  18)  // 1 ETH = 10^18 wei  ⚠ int overflow above ~9.2 ETH
 *   new Currency('USDT', 6)   // 1 USDT = 1_000_000 micro-USDT
 */
final readonly class Currency
{
    public function __construct(
        public readonly string $code,
        public readonly int    $precision,
    ) {
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
            throw new \InvalidArgumentException(
                "Invalid currency code \"{$code}\". Expected 2–10 uppercase alphanumeric characters."
            );
        }
        if ($precision < 0 || $precision > 18) {
            throw new \InvalidArgumentException(
                "Precision must be between 0 and 18, got {$precision}."
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code && $this->precision === $other->precision;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
