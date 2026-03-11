<?php

namespace Payroad\Domain\Event\Attempt;

use DateTimeImmutable;
use Payroad\Domain\Attempt\AttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Event\DomainEvent;
use Payroad\Domain\Payment\PaymentId;

final readonly class AttemptStatusChanged implements DomainEvent
{
    public function __construct(
        public AttemptId         $attemptId,
        public PaymentId         $paymentId,
        public AttemptStatus     $oldStatus,
        public AttemptStatus     $newStatus,
        public string            $providerStatus,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
