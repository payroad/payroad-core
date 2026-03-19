<?php

namespace Payroad\Port\Provider\Cash;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentFlow\Cash\CashPaymentAttempt;
use Payroad\Domain\PaymentFlow\Cash\CashRefund;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;
use Payroad\Port\Provider\Cash\CashRefundContext;
use Payroad\Port\Provider\PaymentProviderInterface;

interface CashProviderInterface extends PaymentProviderInterface
{
    /**
     * Creates a cash attempt and returns a deposit code for the customer.
     */
    public function initiateCashAttempt(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        CashAttemptContext $context
    ): CashPaymentAttempt;

    public function initiateRefund(
        RefundId          $id,
        PaymentId         $paymentId,
        PaymentAttemptId  $originalAttemptId,
        string            $providerName,
        Money             $amount,
        string            $originalProviderReference,
        CashRefundContext  $context
    ): CashRefund;
}
