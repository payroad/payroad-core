<?php

namespace Payroad\Domain\Money;

/**
 * Represents a monetary amount in minor units (the smallest indivisible unit).
 *
 * Works uniformly for fiat and crypto — the decimal precision is carried
 * by the Currency value object, not by Money itself.
 *
 * All arithmetic uses BCMath to handle the full range of supported currencies,
 * including high-precision crypto such as ETH (18 decimals, ~10^18 wei per ETH).
 *
 * @see Currency for precision semantics.
 */
final readonly class Money
{
    public function __construct(
        private string   $minorAmount,
        private Currency $currency
    ) {
        if (bccomp($minorAmount, '0', 0) < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    public static function ofMinor(int $amount, Currency $currency): self
    {
        return new self((string) $amount, $currency);
    }

    /**
     * Constructs Money from a decimal string using BCMath.
     * The precision is taken from the Currency value object.
     *
     * Safe for all currencies including ETH/wei — no integer cast, no overflow.
     */
    public static function ofDecimal(string $amount, Currency $currency): self
    {
        $exp   = $currency->precision;
        $minor = bcmul($amount, bcpow('10', (string) $exp, 0), 0);
        return new self($minor, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self(bcadd($this->minorAmount, $other->minorAmount, 0), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        if (bccomp($other->minorAmount, $this->minorAmount, 0) > 0) {
            throw new \InvalidArgumentException(
                "Cannot subtract {$other->toDecimalString()} {$other->currency->code} "
                . "from {$this->toDecimalString()} {$this->currency->code}: result would be negative."
            );
        }

        return new self(bcsub($this->minorAmount, $other->minorAmount, 0), $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return bccomp($this->minorAmount, $other->minorAmount, 0) > 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->minorAmount, '0', 0) === 0;
    }

    public function equals(self $other): bool
    {
        return bccomp($this->minorAmount, $other->minorAmount, 0) === 0
            && $this->currency->equals($other->currency);
    }

    /**
     * Returns the minor amount as a PHP int.
     *
     * Safe for fiat currencies and low-precision crypto (BTC=8, USDT=6).
     * For ETH and other currencies where amounts may exceed PHP_INT_MAX,
     * use getMinorAmountString() instead.
     *
     * @throws \OverflowException if the minor amount exceeds PHP_INT_MAX
     */
    public function getMinorAmount(): int
    {
        if (bccomp($this->minorAmount, (string) PHP_INT_MAX, 0) > 0) {
            throw new \OverflowException(
                "Minor amount {$this->minorAmount} exceeds PHP_INT_MAX. "
                . "Use getMinorAmountString() for high-precision currencies."
            );
        }
        return (int) $this->minorAmount;
    }

    /**
     * Returns the minor amount as a decimal string.
     *
     * Always safe — use this for crypto currencies with precision >= 10
     * (e.g. ETH with precision=18) where the wei amount exceeds PHP_INT_MAX.
     */
    public function getMinorAmountString(): string
    {
        return $this->minorAmount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function toDecimalString(): string
    {
        $exp = $this->currency->precision;
        if ($exp === 0) {
            return $this->minorAmount;
        }
        return bcdiv($this->minorAmount, bcpow('10', (string) $exp, 0), $exp);
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new \InvalidArgumentException(
                "Currency mismatch: {$this->currency->code} vs {$other->currency->code}."
            );
        }
    }
}
