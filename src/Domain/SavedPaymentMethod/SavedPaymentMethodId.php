<?php

namespace Payroad\Domain\SavedPaymentMethod;

use Ramsey\Uuid\Uuid;

final readonly class SavedPaymentMethodId
{
    private function __construct(public readonly string $value) {}

    public static function fromUuid(string $uuid): self
    {
        if (!Uuid::isValid($uuid)) {
            throw new \InvalidArgumentException("Invalid UUID for SavedPaymentMethodId: \"{$uuid}\".");
        }
        return new self($uuid);
    }

    public static function generate(): self
    {
        return self::fromUuid(Uuid::uuid4()->toString());
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
