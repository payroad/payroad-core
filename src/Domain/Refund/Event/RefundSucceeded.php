<?php

namespace Payroad\Domain\Refund\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;

final readonly class RefundSucceeded extends AbstractDomainEvent
{
    public function __construct(
        public RefundId  $refundId,
        public PaymentId $paymentId,
        public Money     $amount,
    ) {
        parent::__construct();
    }
}
