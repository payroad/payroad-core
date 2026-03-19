<?php

namespace Payroad\Port\Provider\P2P;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentFlow\P2P\P2PPaymentAttempt;
use Payroad\Domain\PaymentFlow\P2P\P2PRefund;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;
use Payroad\Port\Provider\P2P\P2PRefundContext;
use Payroad\Port\Provider\PaymentProviderInterface;

interface P2PProviderInterface extends PaymentProviderInterface
{
    /**
     * Creates a P2P transfer attempt and returns bank transfer instructions.
     */
    public function initiateP2PAttempt(
        PaymentAttemptId  $id,
        PaymentId         $paymentId,
        string            $providerName,
        Money             $amount,
        P2PAttemptContext $context
    ): P2PPaymentAttempt;

    public function initiateRefund(
        RefundId         $id,
        PaymentId        $paymentId,
        PaymentAttemptId $originalAttemptId,
        string           $providerName,
        Money            $amount,
        string           $originalProviderReference,
        P2PRefundContext  $context
    ): P2PRefund;
}
