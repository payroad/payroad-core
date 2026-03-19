<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Domain\Attempt\PaymentAttemptId;

final readonly class VoidCardAttemptCommand
{
    public function __construct(
        public PaymentAttemptId $attemptId,
    ) {}
}
