<?php

namespace Payroad\Port\Provider\Card;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\Channel\Card\CardRefund;
use Payroad\Domain\Refund\RefundId;
use Payroad\Port\Provider\PaymentProviderInterface;

/**
 * Base contract for all card payment providers.
 *
 * Covers the fundamental card flow: initiation and refund.
 *
 * Optional capabilities are expressed as separate interfaces:
 *  - CapturableCardProviderInterface  — authorize + capture / void
 *  - TokenizingCardProviderInterface  — saved card charges and tokenization
 *  - TwoStepCardProviderInterface     — nonce-based server-side charge (Drop-in UI)
 *
 * Use-case layer uses instanceof to check for optional capabilities before calling them.
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

    public function initiateRefund(
        RefundId          $id,
        PaymentId         $paymentId,
        PaymentAttemptId  $originalAttemptId,
        string            $providerName,
        Money             $amount,
        string            $originalProviderReference,
        CardRefundContext  $context
    ): CardRefund;
}
