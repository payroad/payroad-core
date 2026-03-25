<?php

namespace Payroad\Port\Provider\Card;

/**
 * Extension of CardProviderInterface for providers that use a one-step card flow:
 *   1. initiateCardAttempt() → creates a PaymentIntent and returns a clientSecret
 *   2. The frontend (e.g. Stripe.js) collects card details and confirms the charge client-side.
 *      No server-side step 2 is required — the provider notifies via webhook.
 *
 * Providers like Braintree that require a server-side nonce submission
 * do NOT implement this interface — they implement TwoStepCardProviderInterface instead.
 */
interface OneStepCardProviderInterface extends CardProviderInterface
{
}
