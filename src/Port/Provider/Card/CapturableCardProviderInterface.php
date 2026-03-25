<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Domain\Money\Money;

/**
 * Extension for card providers that support the authorize + capture flow.
 *
 * Not all card processors support explicit capture/void — many only offer
 * immediate charge. Providers that do support it implement this interface
 * in addition to CardProviderInterface.
 *
 * Use-case layer checks: instanceof CapturableCardProviderInterface before
 * calling captureAttempt() or voidAttempt(), and returns a domain error if not supported.
 */
interface CapturableCardProviderInterface extends CardProviderInterface
{
    /**
     * Captures a previously authorized amount.
     * Returns a CaptureResult — the use case (not the provider) applies the transition.
     *
     * @param Money|null $amount Partial capture amount. Captures the full authorized amount when null.
     */
    public function captureAttempt(string $providerReference, ?Money $amount = null): CaptureResult;

    /**
     * Voids (releases) a previously authorized amount.
     * Returns a VoidResult — the use case (not the provider) applies the transition.
     */
    public function voidAttempt(string $providerReference): VoidResult;
}
