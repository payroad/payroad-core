<?php

namespace Payroad\Domain\Payment;

/**
 * Arbitrary key-value metadata attached to a payment by the merchant
 * (e.g. orderId, invoiceId, description).
 */
final readonly class PaymentMetadata
{
    public function __construct(private readonly array $data = []) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function with(string $key, mixed $value): self
    {
        return new self(array_merge($this->data, [$key => $value]));
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }
}
