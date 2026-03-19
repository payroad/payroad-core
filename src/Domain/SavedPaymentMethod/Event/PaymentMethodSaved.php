<?php

namespace Payroad\Domain\SavedPaymentMethod\Event;

use Payroad\Domain\AbstractDomainEvent;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;

final readonly class PaymentMethodSaved extends AbstractDomainEvent
{
    public function __construct(
        public SavedPaymentMethodId $savedMethodId,
        public CustomerId           $customerId,
        public PaymentMethodType    $methodType,
        public string               $providerName,
    ) {
        parent::__construct();
    }
}
