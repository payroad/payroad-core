<?php

namespace Payroad\Domain\Payment;

final readonly class CustomerId
{
    public function __construct(public readonly string $value)
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('CustomerId cannot be empty.');
        }
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
