<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Port\Provider\RefundData;

/**
 * Data interface for card-based refunds.
 * Implementations live in provider packages (e.g. payroad/stripe-provider).
 */
interface CardRefundData extends RefundData
{
    /**
     * Reason for the refund as reported by the provider.
     * Examples: "duplicate", "fraudulent", "requested_by_customer".
     */
    public function getReason(): ?string;

    /**
     * Acquirer Reference Number — unique identifier assigned by the card network.
     * Used for dispute resolution and reconciliation.
     */
    public function getAcquirerReferenceNumber(): ?string;
}
