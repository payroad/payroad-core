<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\PaymentFlow\Card\CardRefund;
use Payroad\Domain\PaymentFlow\Card\CardSavedPaymentMethod;
use Payroad\Domain\Refund\RefundId;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;
use Payroad\Port\Provider\Card\CardRefundContext;
use Payroad\Port\Provider\PaymentProviderInterface;

/**
 * Complete contract for card payment providers.
 *
 * Supports:
 *  - Standard card charges (with 3DS context)
 *  - Authorize + Capture (with explicit void/release)
 *  - Tokenized charges via saved card
 *  - Refunds
 *  - Payment method tokenization
 */
interface CardProviderInterface extends PaymentProviderInterface
{
    /**
     * Initiates a card charge. Accepts 3DS and fraud-scoring context from the caller.
     * Returns the fully initialised attempt with providerReference set.
     *
     * The provider creates the CardPaymentAttempt aggregate via CardPaymentAttempt::create().
     */
    public function initiateCardAttempt(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        CardAttemptContext $context
    ): CardPaymentAttempt;

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
     * Captures a previously authorized amount.
     * Returns a CaptureResult — the use case (not the provider) applies the transition.
     *
     * @param Money|null $amount Partial capture amount. Captures the full authorized amount when null.
     */
    public function captureAttempt(
        string $providerReference,
        ?Money $amount = null
    ): CaptureResult;

    /**
     * Voids (releases) a previously authorized amount.
     * Returns a VoidResult — the use case (not the provider) applies the transition.
     */
    public function voidAttempt(string $providerReference): VoidResult;

    public function initiateRefund(
        RefundId          $id,
        PaymentId         $paymentId,
        PaymentAttemptId  $originalAttemptId,
        string            $providerName,
        Money             $amount,
        string            $originalProviderReference,
        CardRefundContext  $context
    ): CardRefund;

    public function savePaymentMethod(
        SavedPaymentMethodId $id,
        CustomerId           $customerId,
        string               $originalProviderReference
    ): CardSavedPaymentMethod;
}
