<?php

namespace Payroad\Domain\Payment\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\PaymentId;

final readonly class PaymentCreated extends AbstractDomainEvent
{
    public function __construct(
        public PaymentId  $paymentId,
        public Money      $amount,
        public CustomerId $customerId,
    ) {
        parent::__construct();
    }
}
