<?php

namespace Payroad\Port\Provider;

use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\AttemptStatus;

/**
 * Returned by PaymentProviderInterface::parseIncomingWebhook() for payment attempt events.
 * Decouples the provider from the aggregate — the provider only parses
 * the payload and returns intent; the application service applies it.
 */
final readonly class WebhookResult extends WebhookResultBase implements WebhookEvent
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

        /**
         * When set, replaces the attempt's providerReference after the transition.
         * Required for two-step providers (e.g. Braintree) where the charge endpoint
         * knows the real transaction ID only after chargeWithNonce() returns.
         */
        ?string            $newProviderReference = null,
    ) {
        parent::__construct($providerReference, $providerStatus, $reason, $statusChanged, $newProviderReference);
    }
}
