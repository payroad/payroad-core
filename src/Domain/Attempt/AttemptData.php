<?php

namespace Payroad\Domain\Attempt;

/**
 * Base interface for provider-specific attempt data.
 * Concrete implementations live in provider integration packages (e.g. payroad/stripe-provider).
 *
 * Use typed sub-interfaces (CardAttemptData, CryptoAttemptData, etc.) instead of this directly.
 *
 * Implementations MUST also provide a static `fromArray(array $data): static` factory
 * used by the infrastructure layer for deserialization. PHP interfaces do not support
 * static methods, so this requirement is enforced by convention and verified at runtime.
 */
interface AttemptData
{
    /** Serialize to a plain array for persistence. */
    public function toArray(): array;
}
