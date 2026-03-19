<?php

namespace Payroad\Domain\Attempt;

/**
 * Base interface for provider-specific attempt data.
 * Concrete implementations live in provider integration packages (e.g. payroad/stripe-provider).
 *
 * Use typed sub-interfaces (CardAttemptData, CryptoAttemptData, etc.) instead of this directly.
 */
interface AttemptData
{
}
