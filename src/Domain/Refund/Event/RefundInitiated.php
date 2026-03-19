<?php

namespace Payroad\Domain\Refund\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;

final readonly class RefundInitiated extends AbstractDomainEvent
{
    public function __construct(
        public RefundId          $refundId,
        public PaymentId         $paymentId,
        public PaymentAttemptId  $originalAttemptId,
        public PaymentMethodType $methodType,
        public string            $providerName,
        public Money             $amount,
    ) {
        parent::__construct();
    }
}
