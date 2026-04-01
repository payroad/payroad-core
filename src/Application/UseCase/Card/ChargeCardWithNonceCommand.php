<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Money;

final readonly class ChargeCardWithNonceCommand
{
    public function __construct(
        public PaymentAttemptId $attemptId,
        public string           $nonce,
        public Money            $amount,
    ) {}
}
