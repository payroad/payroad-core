<?php

namespace Payroad\Domain\Money;

final readonly class Currency
{
    public function __construct(public readonly string $code)
    {
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            throw new \InvalidArgumentException(
                "Invalid currency code \"{$code}\". Expected 3 uppercase letters (ISO 4217)."
            );
        }
    }

    public static function of(string $code): self
    {
        return new self(strtoupper($code));
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
