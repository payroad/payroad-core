<?php

namespace Payroad\Port;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Flow\PaymentSpecificData;

/**
 * Returned by PaymentProviderInterface::parseWebhook().
 * Decouples the provider from the aggregate — the provider only parses
 * the payload and returns intent; the application service applies it.
 */
final readonly class WebhookResult
{
    public function __construct(
        /** Provider-side reference (e.g. Stripe PaymentIntent ID). Used to locate the attempt. */
        public string              $providerReference,

        /** Mapped universal status. */
        public AttemptStatus       $newStatus,

        /** Raw provider status string (e.g. "requires_capture"). Stored on the attempt. */
        public string              $providerStatus,

        /** Human-readable failure reason. */
        public string              $reason = '',

        /**
         * When true, a status transition will be applied.
         * When false, only specificData is updated (e.g. crypto confirmation count increased).
         */
        public bool                $statusChanged = true,

        /** Updated specific data. Null means no data update is needed. */
        public ?PaymentSpecificData $updatedSpecificData = null,
    ) {}
}
