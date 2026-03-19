<?php

namespace Payroad\Domain\Attempt\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\PaymentId;

final readonly class AttemptStatusChanged extends AbstractDomainEvent
{
    public function __construct(
        public PaymentAttemptId $attemptId,
        public PaymentId        $paymentId,
        public AttemptStatus    $oldStatus,
        public AttemptStatus    $newStatus,
        public string           $providerStatus,
    ) {
        parent::__construct();
    }
}
