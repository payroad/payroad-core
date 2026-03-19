<?php

namespace Payroad\Domain\Refund\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\RefundId;

final readonly class RefundFailed extends AbstractDomainEvent
{
    public function __construct(
        public RefundId  $refundId,
        public PaymentId $paymentId,
        public string    $reason,
    ) {
        parent::__construct();
    }
}
