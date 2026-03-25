<?php

namespace Payroad\Port\Provider;

use Payroad\Port\Provider\RefundData;
use Payroad\Domain\Refund\RefundStatus;

/**
 * Returned by PaymentProviderInterface::parseIncomingWebhook() for refund events.
 * Decouples the provider from the aggregate — the provider only parses
 * the payload and returns intent; the application service applies it.
 */
final readonly class RefundWebhookResult extends WebhookResultBase implements WebhookEvent
{
    public function __construct(
        string             $providerReference,

        /** Mapped universal refund status. */
        public RefundStatus $newStatus,

        string             $providerStatus,
        string             $reason = '',
        bool               $statusChanged = true,

        /** Updated refund-specific data. Null means no data update needed. */
        public ?RefundData  $updatedSpecificData = null,
    ) {
        parent::__construct($providerReference, $providerStatus, $reason, $statusChanged);
    }
}
