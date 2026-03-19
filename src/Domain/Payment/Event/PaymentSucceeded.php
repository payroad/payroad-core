<?php

namespace Payroad\Domain\Payment\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;

final readonly class PaymentSucceeded extends AbstractDomainEvent
{
    public function __construct(
        public PaymentId        $paymentId,
        public PaymentAttemptId $attemptId,
    ) {
        parent::__construct();
    }
}
