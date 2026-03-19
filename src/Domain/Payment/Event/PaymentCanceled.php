<?php

namespace Payroad\Domain\Payment\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Payment\PaymentId;

final readonly class PaymentCanceled extends AbstractDomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
    ) {
        parent::__construct();
    }
}
