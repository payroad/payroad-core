<?php

namespace Payroad\Application\UseCase\InitiateAttempt;

use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMethodType;

final readonly class InitiateAttemptCommand
{
    public function __construct(
        public PaymentId          $paymentId,
        public PaymentMethodType  $methodType,
        public string             $providerType,
        public array              $context = []
    ) {}
}
