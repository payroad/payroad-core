<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\CustomerId;

final readonly class SavePaymentMethodCommand
{
    public function __construct(
        public CustomerId $customerId,
        /** The successful attempt whose provider reference will be tokenized. */
        public PaymentAttemptId  $attemptId,
    ) {}
}
