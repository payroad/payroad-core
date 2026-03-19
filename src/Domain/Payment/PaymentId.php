<?php

namespace Payroad\Domain\Payment;

use Ramsey\Uuid\Uuid;

final readonly class PaymentId
{
    private function __construct(public readonly string $value) {}

    public static function fromUuid(string $uuid): self
    {
        if (!Uuid::isValid($uuid)) {
            throw new \InvalidArgumentException("Invalid UUID for PaymentId: \"{$uuid}\".");
        }
        return new self($uuid);
    }

    public static function fromInt(int $id): self
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException("PaymentId int value must be positive, got {$id}.");
        }
        return new self((string) $id);
    }

    /** Convenience method for UUID-based workflows. */
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
