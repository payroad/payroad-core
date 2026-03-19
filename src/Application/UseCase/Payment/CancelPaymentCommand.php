<?php

namespace Payroad\Application\UseCase\Payment;

use Payroad\Domain\Payment\PaymentId;

final readonly class CancelPaymentCommand
{
    public function __construct(
        public PaymentId $paymentId,
    ) {}
}
