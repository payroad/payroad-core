<?php

namespace Payroad\Port\Provider;

/**
 * Shared fields for all parsed webhook results.
 * Subclasses add the flow-specific newStatus and updatedSpecificData.
 */
abstract readonly class WebhookResultBase
{
    public function __construct(
        /** Provider-side reference (e.g. Stripe PaymentIntent ID). Used to locate the aggregate. */
        public string $providerReference,

        /** Raw provider status string (e.g. "requires_capture"). Stored on the aggregate. */
        public string $providerStatus,

        /** Human-readable failure reason. */
        public string $reason = '',

        /**
         * When true, a status transition will be applied.
         * When false, only specificData is updated (e.g. crypto confirmation count increased).
         */
        public bool   $statusChanged = true,
    ) {}
}
