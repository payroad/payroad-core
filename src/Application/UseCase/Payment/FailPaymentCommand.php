<?php

namespace Payroad\Application\UseCase\Payment;

use Payroad\Domain\Payment\PaymentId;

final readonly class FailPaymentCommand
{
    public function __construct(
        public PaymentId $paymentId,
    ) {}
}
