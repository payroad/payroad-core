<?php

namespace Payroad\Domain\Attempt\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Payment\PaymentId;

final readonly class AttemptSucceeded extends AbstractDomainEvent
{
    public function __construct(
        public PaymentAttemptId  $attemptId,
        public PaymentId         $paymentId,
        public PaymentMethodType $methodType,
    ) {
        parent::__construct();
    }
}
