<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Domain\Money\Money;

/**
 * Extension of CardProviderInterface for providers that use a two-step card flow:
 *   1. initiateCardAttempt() → returns a client token for the frontend Drop-in UI
 *   2. chargeWithNonce()     → submits the nonce server-side to complete the charge
 *
 * Providers like Stripe that confirm charges entirely on the client side
 * do NOT implement this interface — they implement OneStepCardProviderInterface instead.
 */
interface TwoStepCardProviderInterface extends CardProviderInterface
{
    /**
     * Submits a client-provided payment method nonce to complete the charge server-side.
     *
     * @throws \RuntimeException on charge failure
     */
    public function chargeWithNonce(string $nonce, Money $amount): ChargeResult;
}
