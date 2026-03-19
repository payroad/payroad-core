<?php

namespace Payroad\Port\Provider;

/**
 * Base interface for all payment provider implementations.
 * Contains only the webhook-parsing contract, which is common to all providers.
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
     * Parses and validates an incoming payment attempt webhook payload.
     * Must NOT modify the attempt — returns a WebhookResult instead.
     */
    public function parseWebhook(array $payload, array $headers): WebhookResult;

    /**
     * Parses and validates an incoming refund webhook payload.
     * Must NOT modify the refund — returns a RefundWebhookResult instead.
     */
    public function parseRefundWebhook(array $payload, array $headers): RefundWebhookResult;
}
