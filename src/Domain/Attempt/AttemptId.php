<?php

namespace Payroad\Domain\Attempt;

use Ramsey\Uuid\Uuid;

final readonly class AttemptId
{
    public function __construct(public readonly string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException("Invalid AttemptId: \"{$value}\".");
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
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
