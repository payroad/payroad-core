<?php

namespace Payroad\Port\Provider;

use Payroad\Port\Provider\Card\CardProviderInterface;
use Payroad\Port\Provider\Cash\CashProviderInterface;
use Payroad\Port\Provider\Crypto\CryptoProviderInterface;
use Payroad\Port\Provider\P2P\P2PProviderInterface;

/**
 * Central registry for typed payment provider resolution.
 *
 * Per-flow methods (forCard, forCrypto, forP2P, forCash) return typed interfaces
 * with flow-specific initiation, capture, void, and refund methods.
 *
 * getByName() — kept generic for webhook parsing (method type unknown at that point).
 */
interface ProviderRegistryInterface
{
    /**
     * Returns the Card provider for a given providerName.
     * Base: initiateCardAttempt, initiateRefund.
     * Flow type (mutually exclusive, check instanceof to distinguish):
     *   OneStepCardProviderInterface     — charge confirmed client-side (e.g. Stripe.js); webhook finalises
     *   TwoStepCardProviderInterface     — chargeWithNonce() called server-side after frontend returns nonce
     * Optional capabilities (cast + check instanceof before calling):
     *   CapturableCardProviderInterface  — captureAttempt, voidAttempt
     *   TokenizingCardProviderInterface  — initiateAttemptWithSavedMethod, savePaymentMethod
     *
     * @throws \Payroad\Application\Exception\ProviderNotFoundException
     */
    public function forCard(string $providerName): CardProviderInterface;

    /**
     * Returns the Crypto provider for a given providerName.
     * Supports: initiateCryptoAttempt.
     * For refunds, cast to RefundableCryptoProviderInterface and check instanceof first.
     *
     * @throws \Payroad\Application\Exception\ProviderNotFoundException
     */
    public function forCrypto(string $providerName): CryptoProviderInterface;

    /**
     * Returns the P2P provider for a given providerName.
     * Supports: initiateP2PAttempt, initiateRefund.
     *
     * @throws \Payroad\Application\Exception\ProviderNotFoundException
     */
    public function forP2P(string $providerName): P2PProviderInterface;

    /**
     * Returns the Cash provider for a given providerName.
     * Supports: initiateCashAttempt, initiateRefund.
     *
     * @throws \Payroad\Application\Exception\ProviderNotFoundException
     */
    public function forCash(string $providerName): CashProviderInterface;

    /**
     * Returns the base provider for webhook parsing.
     * Used by HandleWebhookUseCase and HandleRefundWebhookUseCase.
     *
     * @throws \Payroad\Application\Exception\ProviderNotFoundException
     */
    public function getByName(string $providerName): PaymentProviderInterface;
}
