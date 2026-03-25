<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Domain\Attempt\AttemptStatus;

/**
 * Returned by CardProviderInterface::captureAttempt().
 * The use case (not the provider) applies the transition to the aggregate.
 */
final readonly class CaptureResult
{
    public function __construct(
        public AttemptStatus $newStatus,
        public string        $providerStatus,
    ) {}
}
