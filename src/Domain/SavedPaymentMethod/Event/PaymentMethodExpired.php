<?php

namespace Payroad\Domain\SavedPaymentMethod\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;

final readonly class PaymentMethodExpired extends AbstractDomainEvent
{
    public function __construct(
        public SavedPaymentMethodId $savedMethodId,
        public CustomerId           $customerId,
    ) {
        parent::__construct();
    }
}
