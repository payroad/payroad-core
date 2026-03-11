<?php

namespace Payroad\Port;

use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Flow\PaymentSpecificData;
use Payroad\Domain\Money\Money;

interface PaymentProviderInterface
{
    /** Returns true if this provider handles the given providerType string. */
    public function supports(string $providerType): bool;

    /**
     * Builds the initial (empty) SpecificData for a new attempt.
     * Called before PaymentAttempt::create() so the attempt is never without data.
     */
    public function buildInitialSpecificData(): PaymentSpecificData;

    /**
     * Calls the external provider API to initiate the attempt.
     * Expected side-effects:
     *   - $attempt->setProviderReference(...)
     *   - $attempt->updateSpecificData(...)
     */
    public function initiate(PaymentAttempt $attempt, Money $amount): void;

    /**
     * Parses and validates an incoming webhook payload.
     * Must NOT modify the attempt — returns a WebhookResult instead.
     */
    public function parseWebhook(array $payload, array $headers): WebhookResult;
}
