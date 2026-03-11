<?php

namespace Payroad\Domain\Money;

/**
 * Represents a fiat monetary amount in minor units (e.g. cents for USD).
 * All arithmetic uses integer math to avoid floating-point errors.
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
     * Constructs Money from a decimal string (e.g. "19.99") using bcmath
     * to avoid floating-point rounding errors.
     */
    public static function ofDecimal(string $amount, Currency $currency): self
    {
        $minor = (int) bcmul($amount, '100', 0);
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
        return bcdiv((string) $this->minorAmount, '100', 2);
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
