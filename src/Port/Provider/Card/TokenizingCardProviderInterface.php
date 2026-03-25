<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\PaymentFlow\Card\CardSavedPaymentMethod;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;

/**
 * Extension for card providers that support payment method tokenization.
 *
 * Not all card processors support saving cards for future off-session charges.
 * Providers that do implement this interface in addition to CardProviderInterface.
 *
 * Use-case layer checks: instanceof TokenizingCardProviderInterface before
 * calling savePaymentMethod() or initiateAttemptWithSavedMethod().
 */
interface TokenizingCardProviderInterface extends CardProviderInterface
{
    /**
     * Initiates a charge using a previously stored provider token.
     */
    public function initiateAttemptWithSavedMethod(
        PaymentAttemptId $id,
        PaymentId        $paymentId,
        string           $providerName,
        Money            $amount,
        string           $providerToken
    ): CardPaymentAttempt;

    /**
     * Tokenizes the card used in a successful attempt for future off-session charges.
     */
    public function savePaymentMethod(
        SavedPaymentMethodId $id,
        CustomerId           $customerId,
        string               $originalProviderReference
    ): CardSavedPaymentMethod;
}
