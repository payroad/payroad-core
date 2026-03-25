<?php

namespace Payroad\Port\Provider;

/**
 * Base interface for all payment provider implementations.
 *
 * For initiating payments use the typed sub-interfaces:
 * CardProviderInterface, CryptoProviderInterface,
 * P2PProviderInterface, CashProviderInterface.
 */
interface PaymentProviderInterface
{
    /** Returns true if this provider handles the given providerName string. */
    public function supports(string $providerName): bool;

    /**
     * Parses and validates an incoming webhook payload (attempt or refund event).
     *
     * Returns:
     *  - WebhookResult        for payment attempt events
     *  - RefundWebhookResult  for refund events
     *  - null                 for events the provider does not need to process
     *
     * Must NOT modify any aggregate — the controller routes to the correct use case
     * via instanceof on the returned WebhookEvent.
     */
    public function parseIncomingWebhook(array $payload, array $headers): ?WebhookEvent;
}
