<?php

namespace Payroad\Domain\Event\Payment;

use DateTimeImmutable;
use Payroad\Domain\Event\DomainEvent;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\MerchantId;
use Payroad\Domain\Payment\PaymentId;

final readonly class PaymentCreated implements DomainEvent
{
    public function __construct(
        public PaymentId   $paymentId,
        public Money       $amount,
        public MerchantId  $merchantId,
        public CustomerId  $customerId,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
