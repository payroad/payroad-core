<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Domain\Attempt\AttemptStatus;

/**
 * Returned by CardProviderInterface::voidAttempt().
 * The use case (not the provider) applies the transition to the aggregate.
 */
final readonly class VoidResult
{
    public function __construct(
        public AttemptStatus $newStatus,
        public string        $providerStatus,
        public string        $reason = '',
    ) {}
}
