<?php

namespace Payroad\Domain\Money;

/**
 * Represents a monetary amount in minor units (the smallest indivisible unit).
 *
 * Works uniformly for fiat and crypto — the decimal precision is carried
 * by the Currency value object, not by Money itself.
 *
 * All arithmetic uses integer math to avoid floating-point errors.
 *
 * @see Currency for precision semantics and the ETH/wei overflow warning.
 */
final readonly class Money
{
    public function __construct(
        private int      $minorAmount,
        private Currency $currency
    ) {
        if ($minorAmount < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    public static function ofMinor(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * Constructs Money from a decimal string using bcmath.
     * The precision is taken from the Currency value object.
     *
     * Prefer this factory for high-precision crypto amounts where the raw
     * minor-unit value may approach PHP int range (e.g. large ETH amounts in wei).
     */
    public static function ofDecimal(string $amount, Currency $currency): self
    {
        $exp   = $currency->precision;
        $minor = (int) bcmul($amount, bcpow('10', (string) $exp, 0), 0);
        return new self($minor, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->minorAmount > $this->minorAmount) {
            throw new \InvalidArgumentException(
                "Cannot subtract {$other->toDecimalString()} {$other->currency->code} "
                . "from {$this->toDecimalString()} {$this->currency->code}: result would be negative."
            );
        }

        return new self($this->minorAmount - $other->minorAmount, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minorAmount > $other->minorAmount;
    }

    public function isZero(): bool
    {
        return $this->minorAmount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorAmount === $other->minorAmount
            && $this->currency->equals($other->currency);
    }

    public function getMinorAmount(): int
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
            return (string) $this->minorAmount;
        }
        return bcdiv((string) $this->minorAmount, bcpow('10', (string) $exp, 0), $exp);
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
