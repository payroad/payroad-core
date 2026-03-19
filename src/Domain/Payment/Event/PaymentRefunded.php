<?php

namespace Payroad\Domain\Payment\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;

final readonly class PaymentRefunded extends AbstractDomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
        public RefundId  $refundId,
        public Money     $totalRefundedAmount,
    ) {
        parent::__construct();
    }
}
