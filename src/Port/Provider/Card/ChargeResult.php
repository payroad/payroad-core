<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Domain\Attempt\AttemptStatus;

/**
 * Returned by CardProviderInterface::chargeWithNonce().
 * The controller (not the provider) applies the status transition via HandleWebhookUseCase.
 */
final readonly class ChargeResult
{
    public function __construct(
        public string        $transactionId,
        public AttemptStatus $newStatus,
        public string        $providerStatus,
    ) {}
}
