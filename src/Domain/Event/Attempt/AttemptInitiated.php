<?php

namespace Payroad\Domain\Event\Attempt;

use DateTimeImmutable;
use Payroad\Domain\Attempt\AttemptId;
use Payroad\Domain\Event\DomainEvent;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMethodType;

final readonly class AttemptInitiated implements DomainEvent
{
    public function __construct(
        public AttemptId          $attemptId,
        public PaymentId          $paymentId,
        public PaymentMethodType  $methodType,
        public string             $providerType,
        public DateTimeImmutable  $occurredOn = new DateTimeImmutable()
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
