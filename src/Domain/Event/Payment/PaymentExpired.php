<?php

namespace Payroad\Domain\Event\Payment;

use DateTimeImmutable;
use Payroad\Domain\Event\DomainEvent;
use Payroad\Domain\Payment\PaymentId;

final readonly class PaymentExpired implements DomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
