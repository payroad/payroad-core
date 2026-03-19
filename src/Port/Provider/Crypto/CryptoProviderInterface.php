<?php

namespace Payroad\Port\Provider\Crypto;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentFlow\Crypto\CryptoPaymentAttempt;
use Payroad\Domain\PaymentFlow\Crypto\CryptoRefund;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;
use Payroad\Port\Provider\Crypto\CryptoRefundContext;
use Payroad\Port\Provider\PaymentProviderInterface;

interface CryptoProviderInterface extends PaymentProviderInterface
{
    /**
     * Creates a crypto attempt and returns a deposit wallet address.
     * The context specifies the network and optional memo.
     */
    public function initiateCryptoAttempt(
        PaymentAttemptId     $id,
        PaymentId            $paymentId,
        string               $providerName,
        Money                $amount,
        CryptoAttemptContext $context
    ): CryptoPaymentAttempt;

    public function initiateRefund(
        RefundId            $id,
        PaymentId           $paymentId,
        PaymentAttemptId    $originalAttemptId,
        string              $providerName,
        Money               $amount,
        string              $originalProviderReference,
        CryptoRefundContext $context
    ): CryptoRefund;
}
