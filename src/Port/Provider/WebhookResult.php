<?php

namespace Payroad\Port\Provider;

use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\AttemptStatus;

/**
 * Returned by PaymentProviderInterface::parseWebhook().
 * Decouples the provider from the aggregate — the provider only parses
 * the payload and returns intent; the application service applies it.
 */
final readonly class WebhookResult extends WebhookResultBase
{
    public function __construct(
        string             $providerReference,

        /** Mapped universal attempt status. */
        public AttemptStatus $newStatus,

        string             $providerStatus,
        string             $reason = '',
        bool               $statusChanged = true,

        /** Updated specific data. Null means no data update is needed. */
        public ?AttemptData $updatedSpecificData = null,
    ) {
        parent::__construct($providerReference, $providerStatus, $reason, $statusChanged);
    }
}
