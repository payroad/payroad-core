<?php

namespace Payroad\Domain\Payment\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Payment\PaymentId;

/** Emitted when the first attempt is initiated and the payment moves PENDING → PROCESSING. */
final readonly class PaymentProcessingStarted extends AbstractDomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
    ) {
        parent::__construct();
    }
}
